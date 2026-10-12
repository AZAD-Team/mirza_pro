<?php
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
    ]);
} else {
    ini_set('session.cookie_httponly', '1');
    if ($secureCookie) {
        ini_set('session.cookie_secure', '1');
    }
}
session_start();
session_regenerate_id(true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
$allowed_ips = select("setting","*",null,null,"select");

$user_ip = $_SERVER['REMOTE_ADDR'];
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$check_ip = (!empty($allowed_ips['iplogin']) && $allowed_ips['iplogin'] === $user_ip) ? true : false;
$texterrr = "";
$_SESSION["user"] = null;

/**
 * Best-effort login throttle keyed by client IP. Keeps brute-force attempts
 * low even if the IP allow-list is misconfigured; failures to read/write the
 * state never block a legitimate login.
 */
function panelLoginThrottle($ip, $register)
{
    $dir = __DIR__ . '/.throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . hash('sha256', (string) $ip) . '.json';
    $now = time();
    $window = 900; // 15 minutes
    $maxAttempts = 10;
    $state = ['count' => 0, 'first' => $now];
    $raw = @file_get_contents($file);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['count'], $decoded['first'])) {
            $state = $decoded;
        }
    }
    if (($now - (int) $state['first']) > $window) {
        $state = ['count' => 0, 'first' => $now];
    }
    if ($register) {
        $state['count'] = (int) $state['count'] + 1;
        @file_put_contents($file, json_encode($state), LOCK_EX);
    }
    return (int) $state['count'] >= $maxAttempts;
}

if (isset($_POST['login'])) {
    if (!$check_ip) {
        // The login form is only ever shown to an allow-listed IP; reject any
        // credential submission coming from a different address as well.
        $texterrr = 'دسترسی از این آیپی مجاز نیست.';
    } elseif (panelLoginThrottle($user_ip, false)) {
        $texterrr = 'تلاش‌های ناموفق زیاد بود. چند دقیقه بعد دوباره امتحان کنید.';
    } else {
        $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
        $password = (string) ($_POST['password'] ?? '');
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $username, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ( !$result || !hash_equals((string) $result["password"], $password) ) {
            panelLoginThrottle($user_ip, true);
            $texterrr = 'نام کاربری یا رمزعبور وارد شده اشتباه است!';
        } else {
            foreach ($admin_ids as $admin) {
                $texts = "کاربر با نام کاربری $username وارد پنل تحت وب شد";
                sendmessage($admin, $texts, null, 'html');
            }
            $_SESSION["user"] = $result["username"];
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="Mosaddek">
    <meta name="keyword" content="FlatLab, Dashboard, Bootstrap, Admin, Template, Theme, Responsive, Fluid, Retina">
    <link rel="shortcut icon" href="img/favicon.html">

    <title>ورود به پنل مدیریت</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <!--external css-->
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <!-- Custom styles for this template -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 tooltipss and media queries -->
    <!--[if lt IE 9]>
    <script src="js/html5shiv.js"></script>
    <script src="js/respond.min.js"></script>
    <![endif]-->
</head>

  <body class="login-body">
    <div class="container">
        <?php if(!$check_ip){?>
        <div class="error-card">
            
            <div class="error-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm-1-5h2v2h-2v-2zm0-8h2v6h-2V7z"/>
                </svg>
            </div>
            
            <h1>دسترسی محدود شده</h1>
            <p>جهت استفاده از پنل تحت وب باید از داخل ربات زیر پیام اطلاعات ورود دکمه تنظیم آیپی ورود را زده و آیپی زیر را در آنجا ارسال کنید تا بتوانید استفاده نمایید.</p>
            
            <div class="ip-address" id="user-ip"><?php echo $user_ip; ?></div>
        </div>
        <?php } ?>
        <?php if($check_ip){?>
      <form method="post" class="form-signin" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <h2 class="form-signin-heading">پنل مدیریت ربات میرزا</h2>
        <div class="login-wrap">
            <p><?php echo $texterrr; ?></p>
            <input type="text" name="username" class="form-control" placeholder="نام کاربری" autofocus>
            <input type="password" name="password" class="form-control" placeholder="کلمه عبور">
            <button class="btn btn-lg btn-login btn-block" name="login" type="submit">ورود</button>
        </div>

      </form>
      <?php } ?>
    </div>


  </body>
</html>
