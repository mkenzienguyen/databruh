<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up &mdash; databruh</title>
<link rel="stylesheet" href="/css_files/signup.css">
</head>
<body>

<header>
    <a class="logo" href="./home_page.php">databruh</a>
    <nav>
        <a href="./home_page.php">Home</a>
        <a href="./login.php">Log In</a>
    </nav>
</header>

<main>
    <h1>Sign Up</h1>
    <p class="subtitle">Create an account to get started with databruh.</p>

    <form action="signup_process.php" method="POST">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit">Create Account</button>
    </form>

    <p class="footnote">Already have an account? <a href="./login.php">Log in</a></p>
</main>

<footer>
    databruh &mdash; Group 1
</footer>

</body>
</html>