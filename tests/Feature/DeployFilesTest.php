<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployFilesTest extends TestCase
{
    public function test_env_example_co_du_bien_quan_trong(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        foreach ([
            'APP_NAME=NhoSub', 'APP_TIMEZONE=Asia/Ho_Chi_Minh', 'APP_LOCALE=vi',
            'DB_CONNECTION=mysql', 'QUEUE_CONNECTION=database', 'SESSION_DRIVER=database',
            'ADMIN_SEED_ENABLED', 'SEPAY', 'PAYOS',
        ] as $needle) {
            $this->assertStringContainsString($needle, $env, "Thiếu '{$needle}' trong .env.example");
        }
    }

    public function test_env_example_khong_chua_gia_tri_that(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('APP_KEY=base64:', $env);
        $this->assertStringNotContainsString('Admin@123456', $env);
    }

    public function test_htaccess_goc_rewrite_ve_public(): void
    {
        $ht = file_get_contents(base_path('.htaccess'));

        $this->assertStringContainsString('RewriteRule', $ht);
        $this->assertStringContainsString('public/', $ht);
    }

    public function test_readme_co_huong_dan_deploy_shared_hosting(): void
{
    $readme = file_get_contents(base_path('README.md'));

    foreach ([
        'PHP 8.2', 'shared hosting', 'document root', 'public', 'cron',
        'schedule:run', 'SePay', 'PayOS', 'SMTP', 'nhosub:create-admin',
    ] as $needle) {
        $this->assertStringContainsStringIgnoringCase($needle, $readme, "README thiếu '{$needle}'");
    }
}

    public function test_checklist_deploy_co_du_12_muc(): void
    {
        $doc = file_get_contents(base_path('docs/DEPLOY-CHECKLIST.md'));

        for ($i = 1; $i <= 12; $i++) {
            $this->assertStringContainsString("D{$i}", $doc, "Checklist thiếu mục D{$i}");
        }
    }
}
