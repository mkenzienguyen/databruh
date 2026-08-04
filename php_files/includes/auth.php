<?php
declare(strict_types=1);

if (!function_exists('roleDashboardPath')) {
    function roleDashboardPath(string $typeId): string
    {
        return match ($typeId) {
            'ADMIN' => 'dashboard_admin.php',
            'FLEET_MGR' => 'dashboard_fleet_mgr.php',
            'WS_MGR' => 'dashboard_ws_mgr.php',
            'MECHANIC' => 'dashboard_mechanic.php',
            'DRIVER' => 'dashboard_driver.php',
            default => 'home_page.php',
        };
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string $requiredType): void
    {
        if (!isset($_SESSION['AccountID'])) {
            header('Location: login.php?dashboard=required');
            exit();
        }

        $currentType = (string) ($_SESSION['TypeID'] ?? '');

        if ($currentType !== $requiredType) {
            header('Location: ' . roleDashboardPath($currentType));
            exit();
        }
    }
}
