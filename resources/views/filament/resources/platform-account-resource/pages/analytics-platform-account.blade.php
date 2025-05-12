<x-filament::page>
    <h1 class="text-2xl font-bold mb-4">Thống Kê Hiệu Suất: {{ $record->name }}</h1>
    <a
    href="{{ url()->previous() }}"
    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow"
>
    <!-- Heroicon: arrow-left -->
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
         viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 19l-7-7 7-7"/>
    </svg>
    Quay lại
</a>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($headerWidgets as $widget)
            <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">{{ $widget['title'] }}</h2>
                <p class="text-2xl font-bold text-blue-600">{{ $widget['value'] }}</p>
                <p class="text-sm text-gray-500">{{ $widget['description'] }}</p>
            </div>
        @endforeach
    </div>
</x-filament::page>