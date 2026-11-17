<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PaginationViewTest extends TestCase
{
    /** Phân trang phải dùng markup ul.pagination (không phải Tailwind utility classes). */
    public function test_phan_trang_dung_markup_ul_pagination(): void
    {
        $paginator = new LengthAwarePaginator(
            array_fill(0, 10, 'x'),
            65, // tổng 65 bản ghi (như 65 dịch vụ napxu)
            10,
            1,
            ['path' => '/admin/services'],
        );

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('ul class="pagination"', $html);
        // Tailwind utility gây lỗi mũi tên phình to không được phép xuất hiện.
        $this->assertStringNotContainsString('w-5 h-5', $html);
        $this->assertStringNotContainsString('inline-flex', $html);
    }

    /** CSS design system phải có style cho .pagination và giới hạn kích thước. */
    public function test_css_design_system_co_style_pagination(): void
    {
        $css = file_get_contents(public_path('assets/css/app.css'));

        $this->assertStringContainsString('.pagination', $css);
    }
}
