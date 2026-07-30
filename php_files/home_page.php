<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>databruh</title>
        <link rel="stylesheet" href="../css_files/home_page.css">
    </head>
    <body>
            
        <header>
            <div class="logo">Databruh</div>
            <nav>
                <a href="./home_page.php">Home</a>
                <a href="./datavs.php">Dashboard</a>
                <a href="./manage_fleet.php">Manage Fleet</a>
                
                <?php if (isset($_SESSION['AccountID'])): ?>
                    <?php if (isset($_SESSION['TypeID']) && $_SESSION['TypeID'] === 'ADMIN'): ?>
                        <a href="./admin_page.php">Manage Accounts</a>
                    <?php endif; ?>
                    <span class="user-greeting">Hi, <?php echo htmlspecialchars($_SESSION['FullName']); ?></span>
                    <a href="./view_system_log.php">System Log</a>
                    <a class="logout-link" href="./logout_process.php">Log Out</a>
                <?php else: ?>
                    <a href="./login.php">Login</a>
                    <a href="./signup.php">Sign Up</a>
                <?php endif; ?>
            </nav>
        </header>

        <main>
            <div class="eyebrow">Group 1</div>
            <h1>databruh</h1>
            
            <?php if (isset($_GET['login']) && $_GET['login'] == 'success'): ?>
                <div class="success-banner">Successfully logged in! Welcome back.</div>
            <?php endif; ?>

            <p class="description">
                A smart fleet management system that tracks drivers, vehicles, maintenance,
                and parts across our depots, with dashboards built to surface the data that
                matters most.
            </p>
            <p class="description">
                Team members: <br>
                Duong<br>
                Tung<br>
                Viet Anh<br>
                Long<br>
                Hai<br>
            </p>
            <a class="button" href="./datavs.php">View Driver Safety Dashboard</a>
            <a class="button" href="./manage_fleet.php">Manage Fleet</a>

            <section class="info">
                <div>
                    <h3>Group</h3>
                    <p>Group 1</p>
                </div>
                <div>
                    <h3>Project</h3>
                    <p>databruh</p>
                </div>
                <div>
                    <h3>Focus</h3>
                    <p>Smart Fleet Management</p>
                </div>
            </section>
        </main>

        <footer>
            databruh &mdash; Group 1
        </footer>

    </body>
</html>