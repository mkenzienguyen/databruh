<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>databruh</title>
<style>
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        color: #1f2937;
        background: #ffffff;
    }
    header {
        border-bottom: 1px solid #e5e7eb;
        padding: 20px 60px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .logo {
        font-size: 20px;
        font-weight: bold;
        color: #111827;
    }
    nav a {
        color: #4b5563;
        text-decoration: none;
        margin-left: 32px;
        font-size: 15px;
    }
    nav a:hover {
        color: #111827;
    }
    main {
        max-width: 900px;
        margin: 0 auto;
        padding: 100px 60px 80px 60px;
    }
    .eyebrow {
        color: #6b7280;
        font-size: 14px;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }
    h1 {
        font-size: 42px;
        line-height: 1.2;
        margin: 0 0 20px 0;
        color: #111827;
    }
    p.description {
        font-size: 17px;
        line-height: 1.6;
        color: #4b5563;
        max-width: 600px;
        margin: 0 0 36px 0;
    }
    a.button {
        display: inline-block;
        background: #111827;
        color: #ffffff;
        text-decoration: none;
        padding: 12px 26px;
        border-radius: 6px;
        font-size: 15px;
        font-weight: bold;
    }
    a.button:hover {
        background: #1f2937;
    }
    section.info {
        border-top: 1px solid #e5e7eb;
        margin-top: 80px;
        padding-top: 40px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 40px;
    }
    section.info div h3 {
        font-size: 15px;
        color: #6b7280;
        margin: 0 0 8px 0;
        font-weight: normal;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    section.info div p {
        font-size: 16px;
        margin: 0;
        color: #111827;
    }
    footer {
        border-top: 1px solid #e5e7eb;
        padding: 24px 60px;
        font-size: 13px;
        color: #9ca3af;
        text-align: center;
    }
</style>
</head>
<body>

<header>
    <div class="logo">Databruh</div>
    <nav>
        <a href="./index.php">Home</a>
        <a href="./datavs.php">Dashboard</a>
        <a href="./login.php">Login</a>
    </nav>
</header>

<main>
    <div class="eyebrow">Group 1</div>
    <h1>databruh</h1>
    <p class="description">
        A smart fleet management system that tracks drivers, vehicles, maintenance,
        and parts across our depots, with dashboards built to surface the data that
        matters most.
    </p>
    <p class = "description">
        Team members: <br>
        Duong<br>
        Tung<br>
        Viet Anh<br>
        Long<br>
        Hai<br>
    </p>
    <a class="button" href="./datavs.php">View Driver Safety Dashboard</a>

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