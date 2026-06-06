<?php
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'config.php'; // SMTP credentials — excluded from Git

$mail = new PHPMailer(true);

try {
    // ── Input sanitisation ──
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $totalCO2     = number_format((float)($_POST['emailtotalCO2'] ?? 0), 2);
    $co2Float     = (float)$totalCO2;
    $electricity  = round((float)($_POST['electricity']   ?? 0), 1);
    $people       = (int)($_POST['people']       ?? 1);
    $chiller      = round((float)($_POST['chiller']       ?? 0), 1);
    $oil          = round((float)($_POST['oil']           ?? 0), 2);
    $gas          = round((float)($_POST['gas']           ?? 0), 2);
    $waterQty     = round((float)($_POST['waterQuantity'] ?? 0), 1);
    $weeklyKm     = round((float)($_POST['weeklyKm']      ?? 0), 0);
    $shortHaul    = (int)($_POST['shortHaul']    ?? 0);
    $mediumHaul   = (int)($_POST['mediumHaul']   ?? 0);
    $longHaul     = (int)($_POST['longHaul']     ?? 0);
    $totalSpend   = round((float)($_POST['totalSpend']    ?? 0), 0);

    // ── Category CO₂ values (tonnes) ──
    $catElec      = (float)($_POST['co2_electricity'] ?? 0);
    $catChiller   = (float)($_POST['co2_chiller']     ?? 0);
    $catGas       = (float)($_POST['co2_gas']         ?? 0);
    $catWater     = (float)($_POST['co2_water']       ?? 0);
    $catOil       = (float)($_POST['co2_oil']         ?? 0);
    $catTransport = (float)($_POST['co2_transport']   ?? 0);
    $catFlights   = (float)($_POST['co2_flights']     ?? 0);
    $catDiet      = (float)($_POST['co2_diet']        ?? 0);
    $catSpending  = (float)($_POST['co2_spending']    ?? 0);

    // ── Validate email ──
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    // ── Label helpers ──
    $gasTypeRaw = $_POST['gasType'] ?? 'cylinder';
    $gasLabel   = $gasTypeRaw === 'm3' ? 'm³/Month' : 'Cylinders/Month';

    $waterRaw   = $_POST['water'] ?? 'cume';
    $waterLabel = match($waterRaw) {
        'ltr'  => 'Litres',
        'imga' => 'Imperial Gallons',
        default => 'Cubic Metres',
    };

    $vehicleRaw   = $_POST['vehicleType'] ?? 'medium';
    $vehicleLabel = match($vehicleRaw) {
        'small'    => 'Small car (15–18 kmpl)',
        'large'    => 'Large car / SUV (4–6 kmpl)',
        'electric' => 'Electric car (~0.18 kWh/km)',
        default    => 'Medium car (10–12 kmpl)',
    };

    $dietRaw   = $_POST['dailyDiet'] ?? '';
    $dietLabel = match($dietRaw) {
        'low'        => 'Mostly veg, low meat',
        'medium'     => 'Mostly veg, low meat',
        'regular'    => 'Meat 2–3 times a week',
        'large'      => 'Meat 4–5 times a week',
        'extralarge' => 'Meat daily',
        default      => 'Not specified',
    };

    // ── Rating ──
    if ($co2Float < 15) {
        $ratingLabel  = 'Below UAE Average';
        $ratingColor  = '#22c55e';
        $ratingBg     = '#052e16';
        $compMsg      = 'Great work! Your footprint is <strong>' . $totalCO2 . 't</strong> — below the UAE average of 22t.';
        $compIcon     = '🌿';
    } elseif ($co2Float <= 22) {
        $ratingLabel  = 'Near UAE Average';
        $ratingColor  = '#f59e0b';
        $ratingBg     = '#1c1107';
        $compMsg      = 'Your footprint is <strong>' . $totalCO2 . 't</strong> — at or near the UAE average of 22t.';
        $compIcon     = '⚡';
    } else {
        $ratingLabel  = 'Above UAE Average';
        $ratingColor  = '#ef4444';
        $ratingBg     = '#1c0505';
        $compMsg      = 'Your footprint is <strong>' . $totalCO2 . 't</strong> — above the UAE average of 22t. The tips below can help.';
        $compIcon     = '🔥';
    }

    // ── Gauge ──
    $gaugePct   = min(round(($co2Float / 44) * 100), 100);
    $gaugeColor = $co2Float < 15 ? '#22c55e' : ($co2Float <= 22 ? '#f59e0b' : '#ef4444');

    // ── Category breakdown — sorted for personalised tips ──
    $categories = [
        'Electricity' => $catElec,
        'Chiller'     => $catChiller,
        'Diet'        => $catDiet,
        'Transport'   => $catTransport,
        'Flights'     => $catFlights,
        'Gas'         => $catGas,
        'Spending'    => $catSpending,
        'Water'       => $catWater,
        'Cooking Oil' => $catOil,
    ];
    $catIcons = [
        'Electricity' => '⚡',
        'Chiller'     => '❄️',
        'Diet'        => '🥗',
        'Transport'   => '🚗',
        'Flights'     => '✈️',
        'Gas'         => '🔥',
        'Spending'    => '🛍️',
        'Water'       => '💧',
        'Cooking Oil' => '🫙',
    ];
    arsort($categories);
    $maxCat   = max(array_values($categories)) ?: 1;
    $topCats  = array_slice(array_keys($categories), 0, 3);

    // ── Personalised tips per category ──
    $tipsByCategory = [
        'Chiller' => [
            'Set your chiller thermostat to 24°C — each degree lower increases energy use significantly.',
            'Ensure your chiller system is serviced annually for peak efficiency.',
            'Check for duct leaks in your chiller distribution system — leaks waste up to 30% of cooling.',
        ],
        'Electricity' => [
            'Set your AC to 24°C — every degree lower adds ~6% to your electricity bill.',
            'Switch all lighting to LED — they use 75% less energy than incandescents.',
            'Unplug devices on standby: TVs, chargers and appliances add up to 10% of your bill.',
        ],
        'Diet' => [
            'Try 2 meat-free days per week — this alone can cut your diet footprint by 20%.',
            'Choose local UAE produce over imported goods when possible.',
            'Reduce food waste: one-third of all food produced globally is wasted.',
        ],
        'Transport' => [
            'Combine errands into single trips to reduce km driven per week.',
            'Keep tyres properly inflated — under-inflation increases fuel use by up to 3%.',
            'Use the Dubai Metro or Abu Dhabi bus for city commutes when feasible.',
        ],
        'Flights' => [
            'Choose direct flights — takeoff and landing account for most of a flight\'s emissions.',
            'Consider videoconferencing for business meetings instead of short-haul flights.',
            'Offset unavoidable flights through a verified programme like Gold Standard.',
        ],
        'Gas' => [
            'Use a pressure cooker — it cuts cooking time and gas usage by up to 70%.',
            'Match pot size to burner size to avoid wasted heat.',
            'Consider an induction cooktop — it\'s 80–90% efficient vs 40% for gas.',
        ],
        'Spending' => [
            'Buy second-hand where possible — clothing and electronics have high embedded carbon.',
            'Choose quality over quantity: one durable item beats three disposable ones.',
            'Repair before replacing — extends product life and avoids manufacturing emissions.',
        ],
        'Water' => [
            'Fix leaking taps — a dripping tap can waste 20,000 litres per year.',
            'Take showers under 5 minutes — cutting by 2 minutes saves ~30 litres.',
            'Use a bucket instead of a hose to water plants.',
        ],
        'Cooking Oil' => [
            'Reuse frying oil 2–3 times before disposing — reduces both waste and usage.',
            'Switch to an air fryer for healthier, lower-oil cooking.',
            'Dispose of used oil responsibly through UAE recycling collection points.',
        ],
    ];

    // ── Build category bar rows ──
    function barRow($label, $value, $maxCat, $gaugeColor, $catIcons) {
        $pct   = $maxCat > 0 ? min(round(($value / $maxCat) * 100), 100) : 0;
        $icon  = $catIcons[$label] ?? '•';
        $disp  = number_format($value, 2) . 't';
        $bar   = $pct > 0
            ? '<div style="background:' . $gaugeColor . ';width:' . $pct . '%;height:10px;border-radius:5px;"></div>'
            : '<div style="background:rgba(255,255,255,0.08);width:100%;height:10px;border-radius:5px;"></div>';
        return '
        <tr>
          <td style="padding:8px 0 8px 0;width:110px;color:#c8d8a0;font-size:13px;white-space:nowrap;">' . $icon . '&nbsp;' . $label . '</td>
          <td style="padding:8px 12px;">
            <div style="background:rgba(255,255,255,0.08);border-radius:5px;height:10px;overflow:hidden;">
              ' . $bar . '
            </div>
          </td>
          <td style="padding:8px 0;width:48px;text-align:right;color:#a3e635;font-size:13px;font-weight:700;white-space:nowrap;">' . $disp . '</td>
        </tr>';
    }

    $barRows = '';
    foreach ($categories as $label => $value) {
        $barRows .= barRow($label, $value, $maxCat, $gaugeColor, $catIcons);
    }

    // ── Build personalised tips ──
    $tipsHtml = '';
    foreach ($topCats as $i => $cat) {
        $tips   = $tipsByCategory[$cat] ?? [];
        $tip    = $tips[0] ?? '';
        $icon   = $catIcons[$cat] ?? '•';
        $num    = $i + 1;
        $tipsHtml .= '
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid rgba(163,230,53,0.1);">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="width:32px;vertical-align:top;padding-top:2px;">
                  <div style="width:28px;height:28px;border-radius:50%;background:rgba(163,230,53,0.15);text-align:center;line-height:28px;font-size:14px;">' . $icon . '</div>
                </td>
                <td style="padding-left:12px;vertical-align:top;">
                  <div style="color:#a3e635;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Top emitter #' . $num . ': ' . $cat . '</div>
                  <div style="color:#d4e8b0;font-size:13px;line-height:1.55;">' . $tip . '</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>';
    }

    // ── SMTP config (credentials loaded from config.php) ──
    $mail->SMTPDebug  = 0;
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($email);
    $mail->addReplyTo(SMTP_REPLY_TO, 'No Reply');
    $mail->addBCC(SMTP_BCC);

    $mail->isHTML(true);
    $mail->Subject = 'Your Carbon Footprint: ' . $totalCO2 . 't CO₂e/year — MyCarbonFootprint.ae';

    $mail->Body = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Your Carbon Footprint Report</title>
</head>
<body style="margin:0;padding:0;background:#111a06;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111a06;padding:24px 16px;">
  <tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

    <!-- ══ HEADER ══ -->
    <tr>
      <td style="background:#1a2a08;border-radius:16px 16px 0 0;padding:24px 32px;text-align:center;border-bottom:1px solid rgba(163,230,53,0.15);">
        <img src="https://mycarbonfootprint.ae/images/image_co.jpg" alt="My Carbon Footprint UAE" height="44" style="display:inline-block;border-radius:8px;" onerror="">
        <div style="color:#6b8c3a;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;margin-top:14px;">Carbon Footprint Report</div>
        <div style="color:#c8d8a0;font-size:12px;margin-top:4px;">' . date('d F Y') . '</div>
      </td>
    </tr>

    <!-- ══ SCORE HERO ══ -->
    <tr>
      <td style="background:#1e300a;padding:36px 32px 28px;text-align:center;">
        <div style="color:#6b8c3a;font-size:12px;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">Your Annual Footprint</div>
        <div style="font-size:72px;font-weight:900;color:#a3e635;line-height:1;letter-spacing:-4px;">' . $totalCO2 . '</div>
        <div style="color:#c8d8a0;font-size:15px;margin-top:6px;letter-spacing:0.3px;">tonnes of CO&#8322; per year</div>
        <div style="margin-top:20px;display:inline-block;">
          <span style="display:inline-block;background:' . $ratingBg . ';color:' . $ratingColor . ';border:1px solid ' . $ratingColor . ';border-radius:20px;padding:5px 16px;font-size:12px;font-weight:700;letter-spacing:0.5px;">' . $ratingLabel . '</span>
        </div>
        <div style="margin-top:22px;font-size:13px;color:#d4e8b0;background:rgba(0,0,0,0.25);border-radius:10px;padding:12px 16px;line-height:1.55;">' . $compMsg . '</div>
      </td>
    </tr>

    <!-- ══ GAUGE ══ -->
    <tr>
      <td style="background:#1e300a;padding:0 32px 28px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:rgba(255,255,255,0.1);border-radius:8px;height:12px;overflow:hidden;">
              <div style="background:' . $gaugeColor . ';width:' . $gaugePct . '%;height:12px;border-radius:8px;"></div>
            </td>
          </tr>
          <tr>
            <td>
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="color:#6b8c3a;font-size:11px;padding-top:5px;">0t</td>
                  <td style="text-align:center;color:#f59e0b;font-size:11px;font-weight:700;padding-top:5px;">UAE avg: 22t</td>
                  <td style="text-align:right;color:#6b8c3a;font-size:11px;padding-top:5px;">44t+</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- ══ CATEGORY BREAKDOWN ══ -->
    <tr>
      <td style="background:#192608;padding:28px 32px;border-top:1px solid rgba(163,230,53,0.1);">
        <div style="color:#a3e635;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;">Breakdown by Category</div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          ' . $barRows . '
        </table>
      </td>
    </tr>

    <!-- ══ PERSONALISED TIPS ══ -->
    <tr>
      <td style="background:#1a2a08;padding:28px 32px;border-top:1px solid rgba(163,230,53,0.1);">
        <div style="color:#a3e635;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Your Top 3 Actions</div>
        <div style="color:#6b8c3a;font-size:12px;margin-bottom:16px;">Based on your highest-emission categories</div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid rgba(163,230,53,0.15);border-radius:10px;overflow:hidden;">
          ' . $tipsHtml . '
        </table>
      </td>
    </tr>

    <!-- ══ INPUT SUMMARY ══ -->
    <tr>
      <td style="background:#192608;padding:28px 32px;border-top:1px solid rgba(163,230,53,0.1);">
        <div style="color:#a3e635;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;">Your Inputs</div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
          <tr style="background:rgba(163,230,53,0.08);">
            <td style="padding:8px 12px;color:#6b8c3a;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;width:55%;">Category</td>
            <td style="padding:8px 12px;color:#6b8c3a;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Value</td>
          </tr>
          <tr><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Monthly Electricity</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $electricity . ' kWh</td></tr>
          <tr style="background:rgba(163,230,53,0.04);"><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Household Size</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $people . ' ' . ($people === 1 ? 'person' : 'people') . '</td></tr>
          <tr><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Monthly Chiller</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . ($chiller > 0 ? $chiller . ' kWh' : 'Not applicable') . '</td></tr>
          <tr style="background:rgba(163,230,53,0.04);"><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Cooking Gas</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $gas . ' ' . $gasLabel . '</td></tr>
          <tr><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Cooking Oil</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $oil . ' litres/month</td></tr>
          <tr style="background:rgba(163,230,53,0.04);"><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Tap Water</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $waterQty . ' ' . $waterLabel . '/month</td></tr>
          <tr><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Weekly Driving</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $weeklyKm . ' km — ' . $vehicleLabel . '</td></tr>
          <tr style="background:rgba(163,230,53,0.04);"><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Flights / year</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">Short ' . $shortHaul . ' · Medium ' . $mediumHaul . ' · Long ' . $longHaul . '</td></tr>
          <tr><td style="padding:9px 12px;color:#c8d8a0;border-bottom:1px solid rgba(255,255,255,0.05);">Dietary Habits</td><td style="padding:9px 12px;color:#f0f4e3;border-bottom:1px solid rgba(255,255,255,0.05);">' . $dietLabel . '</td></tr>
          <tr style="background:rgba(163,230,53,0.04);"><td style="padding:9px 12px;color:#c8d8a0;">Monthly Spending</td><td style="padding:9px 12px;color:#f0f4e3;">AED ' . number_format($totalSpend, 0) . '</td></tr>
        </table>
      </td>
    </tr>

    <!-- ══ CTA ══ -->
    <tr>
      <td style="background:#1a2a08;padding:28px 32px;text-align:center;border-top:1px solid rgba(163,230,53,0.1);">
        <div style="color:#c8d8a0;font-size:13px;margin-bottom:16px;">Track your progress — recalculate in 30 days to see how you've improved.</div>
        <a href="https://mycarbonfootprint.ae" style="display:inline-block;background:#4d7c0f;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:25px;font-size:14px;font-weight:700;letter-spacing:0.3px;">Recalculate My Footprint →</a>
      </td>
    </tr>

    <!-- ══ SOCIAL ══ -->
    <tr>
      <td style="background:#192608;padding:20px 32px;text-align:center;border-top:1px solid rgba(163,230,53,0.1);">
        <div style="color:#6b8c3a;font-size:12px;margin-bottom:12px;">Follow us for sustainability tips</div>
        <a href="https://www.facebook.com/recall.uae" style="display:inline-block;margin:0 6px;"><img src="https://mycarbonfootprint.ae/images/Facebook_icon.png" alt="Facebook" width="32" height="32" style="border-radius:50%;"></a>
        <a href="https://x.com/recalluae" style="display:inline-block;margin:0 6px;"><img src="https://mycarbonfootprint.ae/images/Twitter__icon.png" alt="X" width="32" height="32" style="border-radius:50%;"></a>
        <a href="https://www.instagram.com/recall.uae" style="display:inline-block;margin:0 6px;"><img src="https://mycarbonfootprint.ae/images/instgram__icon.png" alt="Instagram" width="32" height="32" style="border-radius:50%;"></a>
      </td>
    </tr>

    <!-- ══ FOOTER ══ -->
    <tr>
      <td style="background:#111a06;border-radius:0 0 16px 16px;padding:20px 32px;text-align:center;border-top:1px solid rgba(163,230,53,0.08);">
        <p style="color:#3d5218;font-size:11px;margin:4px 0;">Results are estimates based on UAE-specific emission factors. Actual footprints may vary.</p>
        <p style="color:#3d5218;font-size:11px;margin:4px 0;">&copy; 2024 <a href="https://recallearth.ae/" style="color:#5a7a22;text-decoration:none;">Recall FZCO</a> &nbsp;·&nbsp; <a href="https://mycarbonfootprint.ae" style="color:#5a7a22;text-decoration:none;">mycarbonfootprint.ae</a></p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>
</body>
</html>';

    $mail->AltBody = "Your Carbon Footprint Report\n\nAnnual footprint: {$totalCO2} tonnes of CO2\nUAE average: 22 tonnes per person per year.\n\nRecalculate anytime at https://mycarbonfootprint.ae";

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'Report sent successfully.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not send email: ' . $mail->ErrorInfo]);
}
