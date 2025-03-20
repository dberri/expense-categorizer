<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expense Categorizer</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <div class="container px-4 py-8 mx-auto">
        <h1 class="mb-8 text-3xl font-bold">Expense Categorizer</h1>

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

        <x-add-new-receipt />

        <div class="p-6 bg-white rounded-lg shadow-md">
            <h2 class="mb-4 text-xl font-semibold">Monthly Breakdown</h2>
            @foreach ($monthlyBreakdown as $year => $months)
                @foreach ($months as $month => $categories)
                    <div class="mb-8">
                        <h3 class="mb-4 text-lg font-medium">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($categories as $category)
                                <div class="p-4 rounded-lg bg-gray-50">
                                    <div class="font-medium">{{ $category->category }}</div>
                                    <div class="text-gray-600">R$ {{ number_format($category->total_amount, 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>

        <div class="p-6 mt-8 bg-white rounded-lg shadow-md">
            <h2 class="mb-4 text-xl font-semibold">All Receipts</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Date</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Total Amount</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Discount</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach (App\Models\Receipt::latest('purchase_date')->get() as $receipt)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                    {{ $receipt->purchase_date->format('d M, Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                    R$ {{ number_format($receipt->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                    R$ {{ number_format($receipt->total_discount, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                    <a href="{{ route('receipts.show', $receipt) }}" class="mr-3 text-indigo-600 hover:text-indigo-900">View Details</a>
                                    <a href="{{ $receipt->receipt_url }}" target="_blank" class="text-blue-600 hover:text-blue-900">View Receipt</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 
