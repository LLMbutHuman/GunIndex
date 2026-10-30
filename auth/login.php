<?php
// Zentrale Konfiguration einbinden
require_once('../config/db.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Wenn bereits eingeloggt, ab zum Dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit;
}

$error = "";
$info = "";

// Check für URL-Nachrichten (z.B. nach Global Logout)
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'session_expired_by_admin':
            $info = "Sicherheits-Reset: Alle Sitzungen wurden terminiert. Bitte neu anmelden.";
            break;
        case 'global_reset_active':
            $info = "Globaler Reset durchgeführt. Zugriff neu autorisieren.";
            break;
        case 'global_logout_success':
            $info = "System-Abmeldung erfolgreich durchgeführt.";
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login']);
    $password = $_POST['password'];

    if (!empty($loginInput) && !empty($password)) {
        // Suche nach E-Mail ODER Username
        $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$loginInput, $loginInput]);
        $user = $stmt->fetch();

        // Passwort-Check
        if ($user && password_verify($password, $user['password_hash'])) {
            // --- SESSION INITIALISIERUNG ---
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];
            
            // WICHTIG: Login-Zeitpunkt für Global Logout Check speichern
            $_SESSION['login_at'] = time(); 

            // Weiterleitung basierend auf Rolle
            if ($_SESSION['is_admin'] === 1) {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit;
        } else {
            $error = "Identifikation fehlgeschlagen. Zugriff verweigert.";
        }
    } else {
        $error = "Bitte füllen Sie alle Identifikationsfelder aus.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | THEGUNSITE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #030303;
            color: #e5e7eb;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-image: radial-gradient(at 50% 50%, rgba(138, 43, 226, 0.08) 0px, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .glass-panel {
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: #8A2BE2;
            background: rgba(255, 255, 255, 0.05);
            outline: none;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.2);
        }
        .btn-purple {
            background: linear-gradient(135deg, #8A2BE2 0%, #4B0082 100%);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(138, 43, 226, 0.3);
        }
    </style>
</head>
<body class="antialiased text-white">

    <div class="w-full max-w-md p-6">
        <div class="text-center mb-10">
            <div class="text-3xl font-black tracking-tighter mb-2 italic">
                <span>THE</span><span class="text-[#8A2BE2]">GUN</span>SITE
            </div>
            <p class="text-gray-500 text-[10px] uppercase font-black tracking-[0.4em] italic">Terminal Access // Authorization Required</p>
        </div>

        <div class="glass-panel p-10 rounded-[3rem]">
            
            <?php if(!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-500 text-[10px] font-black uppercase p-4 rounded-xl mb-8 text-center tracking-widest animate-pulse italic">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($info)): ?>
                <div class="bg-[#8A2BE2]/10 border border-[#8A2BE2]/30 text-[#8A2BE2] text-[10px] font-black uppercase p-4 rounded-xl mb-8 text-center tracking-widest italic">
                    ℹ️ <?php echo $info; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-8">
                <div>
                    <label class="text-[10px] font-black text-gray-500 uppercase ml-1 mb-3 block tracking-widest italic">Nutzer namen / E-Mail</label>
                    <input type="text" name="login" required class="input-field w-full px-6 py-5 rounded-2xl text-sm" placeholder="ID eingeben...">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-3 px-1">
                        <label class="text-[10px] font-black text-gray-500 uppercase block tracking-widest italic">Passwort-Sequenz</label>
                    </div>
                    <input type="password" name="password" required class="input-field w-full px-6 py-5 rounded-2xl text-sm" placeholder="••••••••">
                </div>

                <button type="submit" class="btn-purple w-full py-6 rounded-2xl text-white text-[11px] font-black uppercase tracking-[0.4em] mt-6 shadow-xl italic">
                    Einloggen
                </button>
            </form>

            <div class="mt-10 pt-8 border-t border-white/5 text-center">
                <p class="text-gray-500 text-[10px] uppercase font-black tracking-[0.2em] italic">
                    Kein Konto? 
                    <a href="register.php" class="text-[#8A2BE2] ml-2 hover:text-white transition-colors">Regestrierung</a>
                </p>
            </div>
        </div>
        
        <div class="mt-10 text-center">
            <a href="../marketplace/index.php" class="text-[10px] text-gray-600 hover:text-white transition-all uppercase font-black tracking-[0.4em] flex items-center justify-center gap-3 italic">
                <span class="text-lg">←</span> Marktplatz
            </a>
        </div>
    </div>

</body>
</html>