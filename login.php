<?php
session_start();
// Jika sudah login, langsung lempar ke halaman utama
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SinergiCare SMK</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-6 rounded-2xl shadow-md max-w-sm w-full border border-gray-200">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">SinergiCare SMK</h1>
            <p class="text-sm text-gray-500 mt-1">Silakan masuk menggunakan akun staf Anda</p>
        </div>

        <?php if (isset($_SESSION['error_login'])): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-300 text-red-800 text-xs font-semibold rounded-lg">
                ❌ <?php echo $_SESSION['error_login']; unset($_SESSION['error_login']); ?>
            </div>
        <?php endif; ?>

        <form action="actions/proses_login.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Username</label>
                <input type="text" name="username" class="w-full p-2.5 text-sm border rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="username" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Password</label>
                <input type="password" name="password" class="w-full p-2.5 text-sm border rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="••••••••" required>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition shadow-sm">
                Masuk ke Sistem
            </button>
        </form>
    </div>

</body>
</html>