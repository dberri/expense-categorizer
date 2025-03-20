<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptParserService
{
    private string $openaiApiKey;
    private string $openaiApiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
    }

    public function parseReceipt(string $url, string $purchaseDate): Receipt
    {
        Log::info("Starting receipt parsing process", ['url' => $url, 'date' => $purchaseDate]);
        
        try {
            // Fetch the HTML content from the URL
            Log::info("Fetching HTML from URL", ['url' => $url]);
            $response = Http::get($url);
            if (!$response->successful()) {
                Log::error("Failed to fetch receipt HTML", [
                    'url' => $url, 
                    'status' => $response->status(),
                    'reason' => $response->reason()
                ]);
                throw new \Exception('Failed to fetch receipt from URL: ' . $response->reason());
            }

            $html = $response->body();
            Log::debug("Successfully fetched HTML content", ['size' => strlen($html)]);
            
            // Extract items from the HTML table
            Log::info("Extracting items from HTML");
            $items = $this->extractItemsFromHtml($html);
            Log::info("Extracted items from receipt", ['count' => count($items)]);
            
            // Bundle similar items to reduce token usage
            Log::info("Bundling similar items");
            $originalItems = $items;
            $items = $this->bundleItems($items);
            Log::info("Items after bundling", [
                'original_count' => count($originalItems),
                'bundled_count' => count($items),
                'reduction' => count($originalItems) - count($items)
            ]);
            
            // Extract totals from the footer
            Log::info("Extracting totals from receipt footer");
            $totalAmount = $this->extractTotalAmount($html);
            $totalDiscount = $this->extractTotalDiscount($html);
            $totalItems = $this->extractTotalItems($html);
            Log::info("Extracted totals", [
                'totalAmount' => $totalAmount,
                'totalDiscount' => $totalDiscount,
                'totalItems' => $totalItems
            ]);

            // Validate the extracted data
            $calculatedTotal = array_sum(array_column($items, 'total_price'));
            $calculatedItems = count($items);

            Log::info("Validating extracted data", [
                'calculatedTotal' => $calculatedTotal,
                'extractedTotal' => $totalAmount,
                'calculatedItems' => $calculatedItems,
                'extractedItems' => $totalItems
            ]);

            if (abs($calculatedTotal - $totalAmount) > 0.01) {
                Log::warning("Total amount mismatch", [
                    'calculated' => $calculatedTotal,
                    'extracted' => $totalAmount,
                    'difference' => abs($calculatedTotal - $totalAmount)
                ]);
            }

            if ($calculatedItems !== $totalItems) {
                Log::warning("Total items mismatch", [
                    'calculated' => $calculatedItems,
                    'extracted' => $totalItems
                ]);
            }

            // Create receipt record
            Log::info("Creating receipt record in database");
            $receipt = Receipt::create([
                'purchase_date' => $purchaseDate,
                'receipt_url' => $url,
                'total_amount' => $totalAmount,
                'total_discount' => $totalDiscount,
                'raw_items' => $items,
                'original_items' => $originalItems, // Store the original unbundled items as well
            ]);
            Log::info("Receipt record created", ['receipt_id' => $receipt->id]);

            // Categorize items using OpenAI
            Log::info("Starting item categorization with OpenAI");
            $this->categorizeItems($receipt);
            Log::info("Receipt categorization completed", ['receipt_id' => $receipt->id]);

            return $receipt;
        } catch (\Exception $e) {
            Log::error("Error in receipt parsing process", [
                'url' => $url,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function extractItemsFromHtml(string $html): array
    {
        try {
            // Use DOMDocument to parse HTML
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);

            $items = [];
            $rows = $xpath->query('//tr[starts-with(@id, "Item")]');
            Log::debug("Found rows in HTML", ['count' => $rows->length]);

            foreach ($rows as $index => $row) {
                $logContext = ['row_index' => $index];
                
                // Get the item name from the txtTit2 span
                $nameNode = $xpath->query('.//span[@class="txtTit2"]', $row)->item(0);
                if (!$nameNode) {
                    Log::warning("Name node not found for row", $logContext);
                    continue;
                }
                $name = trim($nameNode->textContent);
                $logContext['name'] = $name;

                // Get quantity from Rqtd span
                $quantityNode = $xpath->query('.//span[@class="Rqtd"]', $row)->item(0);
                $quantity = 1; // Default value
                if ($quantityNode) {
                    preg_match('/\d+/', $quantityNode->textContent, $matches);
                    $quantity = floatval($matches[0] ?? 1);
                }
                $logContext['quantity'] = $quantity;

                // Get unit price from RvlUnit span
                $unitPriceNode = $xpath->query('.//span[@class="RvlUnit"]', $row)->item(0);
                $unitPrice = 0;
                if ($unitPriceNode) {
                    preg_match('/[\d,]+/', $unitPriceNode->textContent, $matches);
                    $unitPrice = str_replace(',', '.', $matches[0] ?? 0);
                }
                $logContext['unit_price'] = $unitPrice;

                // Get total price from valor span
                $totalPriceNode = $xpath->query('.//span[@class="valor"]', $row)->item(0);
                $totalPrice = 0;
                if ($totalPriceNode) {
                    $totalPrice = str_replace(',', '.', $totalPriceNode->textContent);
                }
                $logContext['total_price'] = $totalPrice;

                $items[] = [
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => floatval($unitPrice),
                    'total_price' => floatval($totalPrice),
                ];

                Log::debug("Extracted item from row", $logContext);
            }

            Log::info("Item extraction completed", ['total_items' => count($items)]);
            return $items;
        } catch (\Exception $e) {
            Log::error("Error extracting items from HTML", [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function extractTotalDiscount(string $html): float
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);

            // Look for the discount line in the receipt footer
            $discountNode = $xpath->query('//div[@id="totalNota"]//div[@id="linhaTotal"][.//label[contains(text(), "Descontos")]]//span[@class="totalNumb"]')->item(0);
            
            if ($discountNode) {
                $discountValue = floatval(str_replace(',', '.', $discountNode->textContent));
                Log::debug("Extracted discount value", ['value' => $discountValue]);
                return $discountValue;
            }

            Log::warning("Discount node not found in HTML");
            return 0;
        } catch (\Exception $e) {
            Log::error("Error extracting total discount", [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function extractTotalAmount(string $html): float
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);

            // Look for the total amount line in the receipt footer
            $totalNode = $xpath->query('//div[@id="totalNota"]//div[@id="linhaTotal"][.//label[contains(text(), "Valor total")]]//span[@class="totalNumb"]')->item(0);
            
            if ($totalNode) {
                $totalValue = floatval(str_replace(',', '.', $totalNode->textContent));
                Log::debug("Extracted total amount value", ['value' => $totalValue]);
                return $totalValue;
            }

            Log::warning("Total amount node not found in HTML");
            return 0;
        } catch (\Exception $e) {
            Log::error("Error extracting total amount", [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function extractTotalItems(string $html): int
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);

            // Look for the total items line in the receipt footer
            $itemsNode = $xpath->query('//div[@id="totalNota"]//div[@id="linhaTotal"][.//label[contains(text(), "Qtd. total de itens")]]//span[@class="totalNumb"]')->item(0);
            
            if ($itemsNode) {
                $itemsCount = intval($itemsNode->textContent);
                Log::debug("Extracted total items count", ['count' => $itemsCount]);
                return $itemsCount;
            }

            Log::warning("Total items node not found in HTML");
            return 0;
        } catch (\Exception $e) {
            Log::error("Error extracting total items count", [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function categorizeItems(Receipt $receipt): void
    {
        try {
            $items = $receipt->raw_items;
            Log::info("Building prompt for categorization", ['receipt_id' => $receipt->id, 'items_count' => count($items)]);
            
            // Apply existing patterns first
            Log::info("Checking for existing item patterns before calling OpenAI", ['receipt_id' => $receipt->id]);
            $categorizedItems = $this->applyCategoryPatterns($items);
            $remainingItems = [];
            $itemToIndexMap = [];
            
            // Keep track of which items have already been categorized
            $categorizedIndices = [];
            foreach ($categorizedItems as $categoryItems) {
                foreach ($categoryItems as $itemIndex) {
                    $categorizedIndices[$itemIndex] = true;
                }
            }
            
            // Determine which items still need categorization from OpenAI
            foreach ($items as $index => $item) {
                if (!isset($categorizedIndices[$index])) {
                    $remainingItems[] = $item;
                    $itemToIndexMap[count($remainingItems) - 1] = $index; // Map new index to original index
                }
            }
            
            Log::info("Items categorized by patterns", [
                'receipt_id' => $receipt->id,
                'categorized_count' => count($categorizedIndices),
                'remaining_count' => count($remainingItems)
            ]);
            
            // If there are remaining items, use OpenAI to categorize them
            if (count($remainingItems) > 0) {
                $prompt = $this->buildPrompt($remainingItems);
                Log::debug("Prompt built for OpenAI", ['prompt_length' => strlen($prompt)]);
    
                Log::info("Sending request to OpenAI API", ['receipt_id' => $receipt->id]);
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->openaiApiUrl, [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um especialista em categorização de compras de supermercado. Sua tarefa é categorizar cada item em uma categoria específica. Retorne os resultados como um objeto JSON onde as categorias são as chaves e os valores são arrays com os índices dos itens. IMPORTANTE: Retorne apenas o JSON puro sem blocos de código markdown ou decoração. Nada de ```json ou ``` - apenas o objeto JSON puro.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.3,
                ]);
    
                if (!$response->successful()) {
                    Log::error("OpenAI API request failed", [
                        'receipt_id' => $receipt->id,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \Exception('OpenAI API request failed: ' . $response->body());
                }
    
                $responseData = $response->json();
                Log::debug("OpenAI API response received", [
                    'model' => $responseData['model'] ?? 'unknown',
                    'tokens' => $responseData['usage'] ?? 'unknown'
                ]);
                
                $responseContent = $responseData['choices'][0]['message']['content'];
                Log::debug("OpenAI response content", ['content' => $responseContent]);
                
                // Clean up the response content by removing any markdown code block markers
                $cleanedContent = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $responseContent);
                Log::debug("Cleaned response content", ['cleaned_content' => $cleanedContent]);
                
                $openAiCategorizedItems = json_decode($cleanedContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("Failed to parse OpenAI response as JSON", [
                        'receipt_id' => $receipt->id,
                        'error' => json_last_error_msg(),
                        'content' => $responseContent,
                        'cleaned_content' => $cleanedContent
                    ]);
                    throw new \Exception('Failed to parse OpenAI response as JSON: ' . json_last_error_msg());
                }
                
                // Map OpenAI results back to original indices
                foreach ($openAiCategorizedItems as $categoryName => $indices) {
                    if (!isset($categorizedItems[$categoryName])) {
                        $categorizedItems[$categoryName] = [];
                    }
                    
                    foreach ($indices as $remappedIndex) {
                        if (isset($itemToIndexMap[$remappedIndex])) {
                            $originalIndex = $itemToIndexMap[$remappedIndex];
                            $categorizedItems[$categoryName][] = $originalIndex;
                        }
                    }
                }
                
                Log::info("Items categorized by OpenAI", [
                    'receipt_id' => $receipt->id,
                    'categories' => array_keys($openAiCategorizedItems)
                ]);
            }
            
            // Validate that all categories are valid
            $validCategories = [
                'Proteínas',
                'Grãos e Massas',
                'Frutas e Verduras',
                'Laticínios',
                'Bebidas',
                'Higiene e Limpeza',
                'Congelados e Industrializados',
                'Itens para a Casa'
            ];

            // Check for invalid categories
            $invalidCategories = array_diff(array_keys($categorizedItems), $validCategories);
            if (!empty($invalidCategories)) {
                Log::warning("Invalid categories received", [
                    'receipt_id' => $receipt->id,
                    'invalid_categories' => $invalidCategories
                ]);
            }

            // Calculate totals for each category based on the item indices
            $categoryTotals = [];
            
            // Clear any existing item category mappings for this receipt
            Log::info("Clearing any existing item category mappings", ['receipt_id' => $receipt->id]);
            \App\Models\ReceiptItemCategory::where('receipt_id', $receipt->id)->delete();
            
            // Keep track of items that have already been processed to avoid duplicates
            $processedItemIndices = [];
            
            Log::info("Storing item-to-category mappings", ['receipt_id' => $receipt->id]);
            foreach ($categorizedItems as $categoryName => $itemIndices) {
                if (!in_array($categoryName, $validCategories)) {
                    Log::warning("Skipping invalid category", [
                        'receipt_id' => $receipt->id,
                        'category' => $categoryName
                    ]);
                    continue;
                }
                
                Log::debug("Creating or finding category", ['name' => $categoryName]);
                $category = \App\Models\Category::firstOrCreate(['name' => $categoryName]);
                
                $total = 0;
                $itemsProcessed = 0;
                
                foreach ($itemIndices as $index) {
                    // Skip if this item has already been processed
                    if (isset($processedItemIndices[$index])) {
                        Log::debug("Skipping already processed item", [
                            'receipt_id' => $receipt->id,
                            'item_index' => $index,
                            'previous_category' => $processedItemIndices[$index]
                        ]);
                        continue;
                    }
                    
                    if (isset($items[$index])) {
                        $item = $items[$index];
                        $total += $item['total_price'];
                        
                        // Store the item-to-category mapping
                        \App\Models\ReceiptItemCategory::create([
                            'receipt_id' => $receipt->id,
                            'category_id' => $category->id,
                            'item_index' => $index,
                            'item_name' => $item['name'],
                            'item_price' => $item['total_price'],
                        ]);
                        
                        // Mark this item as processed
                        $processedItemIndices[$index] = $categoryName;
                        
                        $itemsProcessed++;
                    } else {
                        Log::warning("Invalid item index", [
                            'receipt_id' => $receipt->id,
                            'category' => $categoryName,
                            'index' => $index,
                            'max_index' => count($items) - 1
                        ]);
                    }
                }
                
                $categoryTotals[$categoryName] = $total;
                
                Log::debug("Stored item-to-category mappings for category", [
                    'receipt_id' => $receipt->id,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'items_processed' => $itemsProcessed,
                    'total' => $total
                ]);
            }
            
            // Attach categories to receipt with totals
            Log::info("Attaching categories with totals to receipt", ['receipt_id' => $receipt->id]);
            
            // Clear any existing category totals for this receipt
            $receipt->categories()->detach();
            
            foreach ($categoryTotals as $categoryName => $amount) {
                $category = \App\Models\Category::where('name', $categoryName)->first();
                
                Log::debug("Attaching category with total to receipt", [
                    'receipt_id' => $receipt->id,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'amount' => $amount
                ]);
                $receipt->categories()->attach($category->id, ['amount' => $amount]);
            }
            
            Log::info("Receipt categorization completed successfully", [
                'receipt_id' => $receipt->id,
                'category_count' => count($categoryTotals),
                'total_amount' => array_sum(array_values($categoryTotals))
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to categorize receipt", [
                'receipt_id' => $receipt->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Apply existing category patterns to items before using OpenAI
     * 
     * @param array $items The receipt items
     * @return array Categorized items with category name as key and array of item indices as value
     */
    private function applyCategoryPatterns(array $items): array
    {
        $categorized = [];
        
        foreach ($items as $index => $item) {
            $itemName = $item['name'];
            $pattern = \App\Models\ItemCategoryPattern::findMatchingPattern($itemName);
            
            if ($pattern) {
                $categoryName = $pattern->category->name;
                
                if (!isset($categorized[$categoryName])) {
                    $categorized[$categoryName] = [];
                }
                
                $categorized[$categoryName][] = $index;
                
                Log::debug("Item matched pattern", [
                    'item_name' => $itemName,
                    'pattern' => $pattern->pattern,
                    'match_type' => $pattern->match_type,
                    'category' => $categoryName
                ]);
            }
        }
        
        return $categorized;
    }

    private function buildPrompt(array $items): string
    {
        $itemsText = "Por favor, categorize cada um dos seguintes itens em uma das categorias específicas abaixo:\n\n";
        
        foreach ($items as $index => $item) {
            $itemsText .= "{$index}. {$item['name']}: {$item['quantity']} x R\${$item['unit_price']} = R\${$item['total_price']}\n";
        }

        $itemsText .= "\nCategorias disponíveis:\n";
        $itemsText .= "- Proteínas\n";
        $itemsText .= "- Grãos e Massas\n";
        $itemsText .= "- Frutas e Verduras\n";
        $itemsText .= "- Laticínios\n";
        $itemsText .= "- Bebidas\n";
        $itemsText .= "- Higiene e Limpeza\n";
        $itemsText .= "- Congelados e Industrializados\n";
        $itemsText .= "- Itens para a Casa\n\n";

        $itemsText .= "Retorne um objeto JSON onde as chaves são exatamente os nomes das categorias acima e os valores são arrays contendo os números dos itens que pertencem a cada categoria. ";
        $itemsText .= "Por exemplo: {\"Proteínas\": [0, 3, 5], \"Bebidas\": [1, 2]}. ";
        $itemsText .= "Cada item deve aparecer em apenas uma categoria. ";
        $itemsText .= "Se um item não se encaixar claramente em nenhuma categoria, coloque-o em 'Itens para a Casa'. ";
        $itemsText .= "IMPORTANTE: Responda apenas com o JSON puro, sem formatação markdown, sem usar blocos de código com \`\`\` ou nenhum tipo de decoração.";

        return $itemsText;
    }

    /**
     * Bundle similar items in a receipt to reduce token usage
     * 
     * @param array $items The receipt items
     * @return array Bundled items with combined quantities and prices
     */
    private function bundleItems(array $items): array
    {
        $bundledItems = [];
        $itemMap = [];
        
        // Normalize item names and create item map
        foreach ($items as $index => $item) {
            // Create a standardized key by removing spaces, converting to lowercase
            $normalizedName = $this->normalizeItemName($item['name']);
            
            // Skip items with empty names or prices
            if (empty($normalizedName) || $item['total_price'] <= 0) {
                $bundledItems[] = $item;
                continue;
            }
            
            if (!isset($itemMap[$normalizedName])) {
                // First time seeing this item
                $itemMap[$normalizedName] = count($bundledItems);
                $bundledItems[] = $item;
            } else {
                // Item already exists, combine it
                $existingIndex = $itemMap[$normalizedName];
                $existingItem = $bundledItems[$existingIndex];
                
                // Update quantity and prices
                $newQuantity = $existingItem['quantity'] + $item['quantity'];
                $newTotalPrice = $existingItem['total_price'] + $item['total_price'];
                
                // Calculate new unit price
                $newUnitPrice = $newQuantity > 0 ? $newTotalPrice / $newQuantity : $existingItem['unit_price'];
                
                // Update the item
                $bundledItems[$existingIndex]['quantity'] = $newQuantity;
                $bundledItems[$existingIndex]['total_price'] = $newTotalPrice;
                $bundledItems[$existingIndex]['unit_price'] = $newUnitPrice;
                
                // Add the original indices to track which items were bundled
                if (!isset($bundledItems[$existingIndex]['bundled_indices'])) {
                    $bundledItems[$existingIndex]['bundled_indices'] = [$existingIndex];
                }
                $bundledItems[$existingIndex]['bundled_indices'][] = $index;
                
                // Update the display name to include count if not already done
                if (strpos($existingItem['name'], ' (') === false) {
                    $bundledItems[$existingIndex]['name'] = $existingItem['name'] . " ({$newQuantity}x)";
                } else {
                    // Replace the count in the existing name
                    $bundledItems[$existingIndex]['name'] = preg_replace(
                        '/\(\d+x\)/', 
                        "({$newQuantity}x)", 
                        $existingItem['name']
                    );
                }
                
                Log::debug("Bundled items", [
                    'normalized_name' => $normalizedName,
                    'original_name' => $item['name'],
                    'bundled_name' => $bundledItems[$existingIndex]['name'],
                    'new_quantity' => $newQuantity,
                    'new_total' => $newTotalPrice
                ]);
            }
        }
        
        return $bundledItems;
    }
    
    /**
     * Normalize an item name for bundling comparison
     * 
     * @param string $name The item name to normalize
     * @return string The normalized name
     */
    private function normalizeItemName(string $name): string
    {
        // Remove units, quantities, weights, etc.
        $name = preg_replace('/\d+\s*[xX]\s*/', '', $name);
        $name = preg_replace('/\d+\s*(?:g|kg|ml|l)\b/i', '', $name);
        
        // Remove parentheses and their contents
        $name = preg_replace('/\([^)]*\)/', '', $name);
        
        // Remove special characters and extra spaces
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        
        // Trim and convert to lowercase
        return trim(strtolower($name));
    }
} 
