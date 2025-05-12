<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Lượt xem trang</h3>
            <p class="text-2xl">{{ $followers_count }}</p>
            <p class="text-sm text-gray-500">Tổng lượt xem trong 7 ngày qua</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Người dùng tương tác</h3>
            <p class="text-2xl">{{ $engagements }}</p>
            <p class="text-sm text-gray-500">Tổng số người dùng tương tác trong 7 ngày qua</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Lượt tiếp cận</h3>
            <p class="text-2xl">{{ $reach }}</p>
            <p class="text-sm text-gray-500">Tổng lượt tiếp cận trong 7 ngày qua</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Lượt nhấp liên kết</h3>
            <p class="text-2xl">{{ $link_clicks }}</p>
            <p class="text-sm text-gray-500">Tổng số lượt nhấp liên kết trong 7 ngày qua</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Người theo dõi</h3>
            <p class="text-2xl">{{ $followers_count }}</p>
            <p class="text-sm text-gray-500">Số người theo dõi hiện tại</p>
        </div>
    </div>
</x-filament-panels::page>