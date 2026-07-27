<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In &mdash; databruh</title>
    <link rel="stylesheet" href="../css_files/login.css">
</head>
<body>

<header>
    <a class="logo" href="./home_page.php">databruh</a>
    <nav>
        <a href="./home_page.php">Home</a>
        <a href="./signup.php">Sign Up</a>
    </nav>
</header>

<main>
    <h1>Log In</h1>
    <p class="subtitle">Sign in to access the databruh dashboard.</p>

    <form action="login_process.php" method="POST">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Log In</button>
    </form>

    <p class="footnote">Don't have an account? <a href="./signup.php">Sign up</a></p>
</main>

<footer>
    databruh &mdash; Group 1
</footer>

</body>
</html>