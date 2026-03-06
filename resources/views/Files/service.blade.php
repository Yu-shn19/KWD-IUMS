<div class="min-h-screen bg-gray-100 p-6">
    <!-- Header / Navigation -->
    <div class="bg-blue-700 text-white rounded-t-xl shadow-md flex items-center justify-between px-6 py-3">
        <h1 class="text-xl font-semibold">Consumers</h1>
        <div class="space-x-2">
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm">F10 - New</button>
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm">F11 - Edit</button>
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm">F12 - Delete</button>
            <button class="bg-gray-200 text-blue-700 px-3 py-1 rounded-md text-sm font-medium">Search</button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white shadow-md rounded-b-xl p-6">
        <!-- Consumer Informat  ion -->
        <h2 class="text-blue-700 font-semibold text-lg mb-3 border-b border-blue-200 pb-1">Information</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="font-medium">Account:</span> <span>00123</span>
            </div>
            <div>
                <span class="font-medium">Name:</span> <span>Juan Dela Cruz</span>
            </div>
            <div>
                <span class="font-medium">Address:</span> <span>Poblacion, Digos City</span>
            </div>
            <div>
                <span class="font-medium">Meter No.:</span> <span>MT-2024-0876</span>
            </div>
            <div>
                <span class="font-medium">Installation Date:</span> <span>2023-05-14</span>
            </div>
            <div>
                <span class="font-medium">Status:</span> <span class="text-green-600">Active</span>
            </div>
        </div>

        <!-- Charges Section -->
        <div class="mt-8">
            <h2 class="text-blue-700 font-semibold text-lg mb-2 border-b border-blue-200 pb-1">Charges</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-blue-50 text-blue-700">
                        <tr>
                            <th class="border px-3 py-2 text-left">Charge Type</th>
                            <th class="border px-3 py-2 text-left">Amount</th>
                            <th class="border px-3 py-2 text-left">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2">Water Bill</td>
                            <td class="border px-3 py-2">₱350.00</td>
                            <td class="border px-3 py-2">Monthly charge</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2">Maintenance</td>
                            <td class="border px-3 py-2">₱50.00</td>
                            <td class="border px-3 py-2">Service fee</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Meter Reading Section -->
        <div class="mt-8">
            <h2 class="text-blue-700 font-semibold text-lg mb-2 border-b border-blue-200 pb-1">Meter Reading</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-blue-50 text-blue-700">
                        <tr>
                            <th class="border px-3 py-2 text-left">Date</th>
                            <th class="border px-3 py-2 text-left">Previous Reading</th>
                            <th class="border px-3 py-2 text-left">Current Reading</th>
                            <th class="border px-3 py-2 text-left">Consumption</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2">2025-10-01</td>
                            <td class="border px-3 py-2">1250</td>
                            <td class="border px-3 py-2">1275</td>
                            <td class="border px-3 py-2">25</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2">2025-09-01</td>
                            <td class="border px-3 py-2">1225</td>
                            <td class="border px-3 py-2">1250</td>
                            <td class="border px-3 py-2">25</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Latest Bill -->
        <div class="mt-8">
            <h2 class="text-blue-700 font-semibold text-lg mb-2 border-b border-blue-200 pb-1">Latest Bill</h2>
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 text-sm">
                <p><span class="font-medium">Bill No:</span> 2025-09-001</p>
                <p><span class="font-medium">Billing Period:</span> Sept 2025</p>
                <p><span class="font-medium">Total Amount:</span> <span class="text-green-700 font-semibold">₱400.00</span></p>
                <p><span class="font-medium">Due Date:</span> Oct 15, 2025</p>
            </div>
        </div>
    </div>
</div>