<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$ManagePanel = new ManagePanel();
$data = json_decode(file_get_contents("php://input"),true);
if(!is_array($data) || empty($data['PaymentID'])){
    return;
}

/**
 * Verify a TRON payment on-chain instead of trusting the callback body.
 *
 * The callback for this gateway is an unauthenticated public URL and the
 * previous implementation credited the wallet whenever the posted body said
 * `IsPaid`. Because the order id is shown to the buyer, that allowed a free
 * balance top-up without any real payment. The transaction is therefore
 * re-checked against the public chain explorer: it must be confirmed, sent to
 * the wallet configured for this bot, and carry at least the requested amount.
 */
function tronadoVerifyOnChain($txid, $expectedWallet, $minTronAmount)
{
    if (!is_string($txid) || !preg_match('/^[A-Fa-f0-9]{64}$/', $txid)) {
        return false;
    }
    if (!is_string($expectedWallet) || !preg_match('/^T[1-9A-HJ-NP-Za-km-z]{25,40}$/', $expectedWallet)) {
        return false;
    }

    $ch = curl_init('https://apilist.tronscanapi.com/api/transaction-info?hash=' . urlencode($txid));
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return false;
    }
    $info = json_decode($response, true);
    if (!is_array($info) || empty($info['confirmed'])) {
        return false;
    }

    $toAddress = $info['toAddress'] ?? ($info['contractData']['to_address'] ?? '');
    $amountSun = isset($info['contractData']['amount']) ? (float) $info['contractData']['amount'] : -1;
    if ($toAddress === '' || $amountSun < 0) {
        return false;
    }
    if (strcasecmp($toAddress, $expectedWallet) !== 0) {
        return false;
    }

    $paidTrx = $amountSun / 1000000;
    $required = (float) $minTronAmount;
    // small tolerance for rounding done at invoice creation time
    if ($required > 0 && ($paidTrx + 0.0001) < ($required * 0.98)) {
        return false;
    }
    return true;
}

$Payment_report = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM Payment_report WHERE id_order = '{$data['PaymentID']}' LIMIT 1"));
if(!$Payment_report)return;
if($Payment_report['payment_Status'] == "expire")return;
if($Payment_report['payment_Status'] == "paid")return;
$setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
$price = $Payment_report['price'];
    $datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
    $datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'textafterpayibsng' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
    if($Payment_report['payment_Status'] != "paid"){
        $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
        $txHash = isset($data['Hash']) ? $data['Hash'] : '';
        $paymentConfirmed = ($data['IsPaid'] ?? false) === true
            && tronadoVerifyOnChain($txHash, $walletaddress, $data['TronAmount'] ?? 0);
        if ($paymentConfirmed && is_string($txHash) && preg_match('/^[A-Fa-f0-9]{64}$/', $txHash)) {
            // reject a transaction hash that was already credited to another order
            $reuseStmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'paid' AND id_order != :id_order AND dec_not_confirmed LIKE :hash");
            $reuseStmt->execute([':id_order' => $Payment_report['id_order'], ':hash' => '%' . $txHash . '%']);
            if ((int) $reuseStmt->fetchColumn() > 0) {
                $paymentConfirmed = false;
            }
        }
        if($paymentConfirmed){
            echo "پرداخت با موفقیت انجام  شد";
    $textbotlang = languagechange('../text.json');
    DirectPayment($data['PaymentID'],"../images.jpg");
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackiranpay2","select")['ValuePay'];
    $Balance_id = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM user WHERE id = '{$Payment_report['id_user']}' LIMIT 1"));
    if($pricecashback != "0"){
       $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) +$result ;
        update("user","Balance",$Balance_confrim, "id",$Balance_id['id']); 
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    if($data['TronAmount'] < $data['ActualTronAmount']){
        $balancelow = "❌ کاربر کمتر از مبلغ تعیین شده واریز کرده است.";
    }
$text_reportpayment = "💵 پرداخت جدید
$balancelow
- 👤 نام کاربری کاربر : @{$Balance_id['username']}
- 🆔آیدی عددی کاربر : {$Balance_id['id']}
- 💸 مبلغ تراکنش $price
- 🔗 <a href = \"https://tronscan.org/#/transaction/{$data['Hash']}\">لینک پرداخت </a>
- 📥 مبلغ واریز شده ترون. : {$data['TronAmount']}
- 💳 روش پرداخت :  ترونادو";
    $stmt = $connect->prepare("UPDATE Payment_report SET payment_Status = ? WHERE id_order = ?");
    $Status_change = "paid";
    $stmt->bind_param("ss", $Status_change, $Payment_report['id_order']);
    $stmt->execute();
    $stmt = $connect->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id_order = ?");
    $database = json_encode($data);
    $stmt->bind_param("ss", $database, $Payment_report['id_order']);
    $stmt->execute();
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_reportpayment,
        'parse_mode' => "HTML"
        ]);
    }
        }
    }