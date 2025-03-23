<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt Details - Expense Categorizer</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="bg-gray-100">
    <div class="container px-4 py-8 mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold">Receipt Details</h1>
            <div class="flex space-x-2">
                <form id="deleteReceiptForm" action="{{ route('receipts.destroy', $receipt) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="openDeleteModal()" class="px-4 py-2 text-white bg-red-600 rounded hover:bg-red-700">
                        Delete Receipt
                    </button>
                </form>
                <a href="{{ route('receipts.index') }}" class="px-4 py-2 text-white bg-gray-600 rounded hover:bg-gray-700">
                    Back to List
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="p-6 mb-8 bg-white rounded-lg shadow-md">
            <h2 class="mb-4 text-xl font-semibold">Receipt Information</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="text-gray-600">Date:</p>
                    <p class="font-medium">{{ $receipt->purchase_date->format('F j, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Total Amount:</p>
                    <p class="font-medium">R$ {{ number_format($receipt->total_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Discount:</p>
                    <p class="font-medium">R$ {{ number_format($receipt->total_discount, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Receipt URL:</p>
                    <a href="{{ $receipt->receipt_url }}" target="_blank" class="text-blue-600 hover:underline">View Original Receipt</a>
                </div>
            </div>
        </div>

        <div class="p-6 bg-white rounded-lg shadow-md">
            <h2 class="mb-4 text-xl font-semibold">Categorized Items</h2>
            
            <div class="mb-4">
                <button id="bundleItemsBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Bundle Selected Items
                </button>
            </div>
            
            @foreach ($categorizedItems as $categoryName => $category)
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">{{ $categoryName }}</h3>
                        <span class="font-semibold text-gray-600">R$ {{ number_format($category['total'], 2) }}</span>
                    </div>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        <div class="flex items-center">
                                            <input type="checkbox" class="w-4 h-4 mr-2 text-indigo-600 border-gray-300 rounded category-select-all focus:ring-indigo-500"
                                                data-category="{{ $categoryName }}">
                                            Item
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-right text-gray-500 uppercase">
                                        Price
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($category['items'] as $item)
                                    <tr class="@if(isset($item['raw_data']['bundled_from'])) font-semibold @endif">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <input type="checkbox" class="w-4 h-4 mr-2 text-indigo-600 border-gray-300 rounded item-select focus:ring-indigo-500"
                                                    data-category="{{ $categoryName }}" 
                                                    data-item-index="{{ $item['raw_data']['index'] }}"
                                                    data-item-name="{{ $item['name'] }}">
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm font-medium text-gray-900">
                                                        {{ $item['name'] }}
                                                        @if($item['quantity'] > 1)
                                                            <span class="text-gray-500">({{ $item['quantity'] }}x)</span>
                                                        @endif
                                                    </span>
                                                    @if(isset($item['raw_data']['bundled_from']))
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                            Bundled
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right text-gray-500 whitespace-nowrap">
                                            R$ {{ number_format($item['price'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-center text-gray-500 whitespace-nowrap">
                                            <button onclick="openChangeCategoryModal('{{ $item['name'] }}', {{ $item['receipt_item_id'] }})" class="px-3 py-1 text-xs text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                                Change Category
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Category Change Modal -->
    <div id="changeCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
        <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium">Change Item Category</h3>
                <button onclick="closeChangeCategoryModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="changeCategoryForm" action="{{ route('receipts.change-item-category') }}" method="POST">
                @csrf
                <input type="hidden" name="receipt_item_id" id="receiptItemId">
                
                <div class="mb-4">
                    <p class="mb-2 text-sm text-gray-500">Item: <span id="modalItemName" class="font-medium"></span></p>
                </div>
                
                <div class="mb-4">
                    <label for="new_category_id" class="block mb-1 text-sm font-medium text-gray-700">New Category</label>
                    <select id="new_category_id" name="new_category_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (App\Models\Category::orderBy('name')->get() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="learn_pattern" name="learn_pattern" value="1" checked class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="learn_pattern" class="block ml-2 text-sm text-gray-700">
                            Remember this categorization for future items
                        </label>
                    </div>
                </div>
                
                <div id="patternOptions" class="mb-4">
                    <label for="pattern_match_type" class="block mb-1 text-sm font-medium text-gray-700">Match Type</label>
                    <select id="pattern_match_type" name="pattern_match_type" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="exact">Exact Match</option>
                        <option value="contains">Contains</option>
                        <option value="starts_with">Starts With</option>
                        <option value="ends_with">Ends With</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">This determines how similar items will be matched in the future.</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="closeChangeCategoryModal()" class="px-4 py-2 mr-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Change Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bundle Items Modal -->
    <div id="bundleItemsModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
        <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium">Bundle Items</h3>
                <button onclick="closeBundleModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="bundleItemsForm" action="{{ route('receipts.bundle-items') }}" method="POST">
                @csrf
                <input type="hidden" name="receipt_id" value="{{ $receipt->id }}">
                <div id="bundleItemIndices"></div>
                
                <div class="mb-4">
                    <p class="mb-2 text-sm text-gray-500">Selected Items:</p>
                    <ul id="selectedItemsList" class="p-3 overflow-y-auto text-sm rounded-md bg-gray-50 max-h-40"></ul>
                </div>
                
                <div class="mb-4">
                    <label for="new_name" class="block mb-1 text-sm font-medium text-gray-700">Bundle Name (Optional)</label>
                    <input type="text" id="new_name" name="new_name" placeholder="Leave blank to use first item's name" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Leave blank to use the first item's name with quantity.</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="closeBundleModal()" class="px-4 py-2 mr-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Bundle Items
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Receipt Modal -->
    <div id="deleteReceiptModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50">
        <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-red-600">Delete Receipt</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="mb-4">
                <p class="text-gray-700">Are you sure you want to delete this receipt from <span class="font-medium">{{ $receipt->purchase_date->format('F j, Y') }}</span>?</p>
                <p class="mt-2 text-gray-700">This action cannot be undone and will delete:</p>
                <ul class="pl-5 mt-2 text-gray-600 list-disc">
                    <li>All receipt items and their categories</li>
                    <li>All category associations</li>
                    <li>The receipt record itself</li>
                </ul>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </button>
                <button type="button" onclick="confirmDelete()" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Delete Receipt
                </button>
            </div>
        </div>
    </div>

    <script>
        function openChangeCategoryModal(itemName, receiptItemId) {
            document.getElementById('modalItemName').textContent = itemName;
            document.getElementById('receiptItemId').value = receiptItemId;
            document.getElementById('changeCategoryModal').classList.remove('hidden');
        }
        
        function closeChangeCategoryModal() {
            document.getElementById('changeCategoryModal').classList.add('hidden');
        }
        
        // Toggle pattern options based on checkbox
        document.getElementById('learn_pattern').addEventListener('change', function() {
            document.getElementById('patternOptions').style.display = this.checked ? 'block' : 'none';
        });
        
        // New scripts for bundling functionality
        document.addEventListener('DOMContentLoaded', function() {
            const bundleBtn = document.getElementById('bundleItemsBtn');
            const selectAllCheckboxes = document.querySelectorAll('.category-select-all');
            const itemCheckboxes = document.querySelectorAll('.item-select');
            
            // Enable/disable bundle button based on checkbox selection
            function updateBundleButton() {
                const checkedItems = document.querySelectorAll('.item-select:checked');
                bundleBtn.disabled = checkedItems.length < 2;
            }
            
            // Add event listeners to checkboxes
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // If unchecked, also uncheck the "select all" for this category
                    if (!this.checked) {
                        const categoryName = this.dataset.category;
                        const selectAllBox = document.querySelector(`.category-select-all[data-category="${categoryName}"]`);
                        if (selectAllBox) selectAllBox.checked = false;
                    }
                    
                    // Only allow selecting items from the same category
                    const checkedCategory = document.querySelector('.item-select:checked')?.dataset.category;
                    if (checkedCategory) {
                        itemCheckboxes.forEach(cb => {
                            if (cb.dataset.category !== checkedCategory) {
                                cb.disabled = true;
                                cb.title = "You can only bundle items from the same category";
                            }
                        });
                        
                        // Also disable select-all checkboxes for other categories
                        selectAllCheckboxes.forEach(cb => {
                            if (cb.dataset.category !== checkedCategory) {
                                cb.disabled = true;
                                cb.title = "You can only bundle items from the same category";
                            }
                        });
                    } else {
                        // Re-enable all checkboxes if nothing is selected
                        itemCheckboxes.forEach(cb => {
                            cb.disabled = false;
                            cb.title = "";
                        });
                        
                        selectAllCheckboxes.forEach(cb => {
                            cb.disabled = false;
                            cb.title = "";
                        });
                    }
                    
                    updateBundleButton();
                });
            });
            
            // Handle "select all" checkboxes
            selectAllCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const categoryName = this.dataset.category;
                    const itemsInCategory = document.querySelectorAll(`.item-select[data-category="${categoryName}"]:not(:disabled)`);
                    
                    itemsInCategory.forEach(item => {
                        item.checked = this.checked;
                        // Trigger the change event
                        const event = new Event('change');
                        item.dispatchEvent(event);
                    });
                });
            });
            
            // Handle bundle button click
            bundleBtn.addEventListener('click', function() {
                openBundleModal();
            });
        });
        
        function openBundleModal() {
            // Get selected items
            const selectedItems = document.querySelectorAll('.item-select:checked');
            const indicesDiv = document.getElementById('bundleItemIndices');
            const selectedItemsList = document.getElementById('selectedItemsList');
            
            // Clear previous content
            indicesDiv.innerHTML = '';
            selectedItemsList.innerHTML = '';
            
            // Add selected items to the modal
            selectedItems.forEach(item => {
                const itemIndex = item.dataset.itemIndex;
                const itemName = item.dataset.itemName;
                
                // Add hidden input for each selected item
                indicesDiv.innerHTML += `<input type="hidden" name="item_indices[]" value="${itemIndex}">`;
                
                // Add item to the list
                const listItem = document.createElement('li');
                listItem.className = 'py-1';
                listItem.textContent = itemName;
                selectedItemsList.appendChild(listItem);
            });
            
            // Show the modal
            document.getElementById('bundleItemsModal').classList.remove('hidden');
        }
        
        function closeBundleModal() {
            document.getElementById('bundleItemsModal').classList.add('hidden');
        }
        
        // Delete Receipt Modal Functions
        function openDeleteModal() {
            document.getElementById('deleteReceiptModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteReceiptModal').classList.add('hidden');
        }
        
        function confirmDelete() {
            document.getElementById('deleteReceiptForm').submit();
        }
    </script>
</body>
</html> 
