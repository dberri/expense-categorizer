@if (session('warning'))
    <div class="relative px-4 py-3 mb-4 text-yellow-700 bg-yellow-100 border border-yellow-400 rounded" role="alert">
        <span class="block sm:inline">{{ session('warning') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="relative px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
@endif

<div class="p-6 mb-8 bg-white rounded-lg shadow-md">
    <h2 class="mb-4 text-xl font-semibold">Add New Receipt</h2>
    <form method="POST" action="{{ route('receipts.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="receipt_url" class="block text-sm font-medium text-gray-700">Receipt URL</label>
            <input type="url" name="receipt_url" id="receipt_url" required
                value="{{ old('receipt_url') }}"
                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="purchase_date" class="block text-sm font-medium text-gray-700">Purchase Date (Optional)</label>
            <input type="date" name="purchase_date" id="purchase_date"
                value="{{ old('purchase_date') }}"
                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Process Receipt
            </button>
        </div>
    </form>
</div>

@if (session('existing_receipt_id'))
<!-- Overwrite Confirmation Modal -->
<div id="overwriteModal" class="fixed inset-0 w-full h-full overflow-y-auto bg-gray-600 bg-opacity-50">
    <div class="relative p-5 mx-auto bg-white border rounded-md shadow-lg top-20 w-96">
        <div class="mt-3 text-center">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Receipt Already Exists</h3>
            <div class="py-3 mt-2 px-7">
                <p class="text-sm text-gray-500">
                    A receipt with this URL already exists. Would you like to overwrite it?
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <form method="POST" action="{{ route('receipts.store') }}" class="inline">
                    @csrf
                    <input type="hidden" name="receipt_url" value="{{ old('receipt_url') }}">
                    <input type="hidden" name="purchase_date" value="{{ old('purchase_date') }}">
                    <input type="hidden" name="overwrite" value="1">
                    <button type="submit" class="px-4 py-2 mr-2 text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Overwrite
                    </button>
                    <button type="button" onclick="closeOverwriteModal()" class="px-4 py-2 text-gray-800 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function closeOverwriteModal() {
        document.getElementById('overwriteModal').style.display = 'none';
    }
</script>
@endif
