# MyCarbonFootprint.ae — UAE Carbon Footprint Calculator

A free, public-awareness carbon footprint calculator for UAE residents, built on the **CFC 2026 Methodology**.

**Live site:** [mycarbonfootprint.ae](https://mycarbonfootprint.ae)
**Preview:** [abdullamusthafa-ops.github.io/mycarbonfootprint](https://abdullamusthafa-ops.github.io/mycarbonfootprint)

---

## Features

- 9 emission categories: Electricity, Chiller, Cooking Gas, Cooking Oil, Tap Water, Transport, Flights, Food & Diet, Lifestyle Spending
- CFC 2026 emission factors (DEWA 0.4045 kg CO₂e/kWh, EPA gas 1.94 kg/m³)
- Electric car support (0.073 kg CO₂e/km)
- Two-input gas section (cylinders or m³)
- Gauge bar vs UAE average (22t/year)
- Personalised email report with category breakdown
- View Calculation Methodology PDF
- WCAG AA accessible — screen reader labels, focus states, touch targets

---

## Emission Factors (CFC 2026)

| Category | Factor | Source |
|---|---|---|
| Electricity & Chiller | 0.4045 kg CO₂e/kWh | DEWA 2024 |
| Gas cylinder | 46 kg CO₂/cylinder | EPA |
| Central gas | 1.94 kg CO₂/m³ | EPA |
| Cooking oil | 0.92 kg/L × 2.6 kg CO₂/kg | Standard |
| Tap water | 2.988 kg CO₂/m³ | UAE estimate |
| Small car | 0.179 kg CO₂e/km | Standard |
| Medium car | 0.268 kg CO₂e/km | Standard |
| Large car/SUV | 0.572 kg CO₂e/km | Standard |
| Electric car | 0.073 kg CO₂e/km | 0.18 kWh/km × 0.4045 |
| Short-haul flight | 0.5t CO₂e/return trip | Estimate |
| Medium-haul flight | 1.0t CO₂e/return trip | Estimate |
| Long-haul flight | 2.5t CO₂e/return trip | Estimate |
| Spending | 50 kg CO₂e per AED 1,000/month | Estimate |

---

## Local Development

```bash
cd mycarbonfootprint
python -m http.server 8080
# Open http://localhost:8080
```

> **Note:** The email report (`mail.php`) requires a PHP server with Composer dependencies. Static preview works on Python server.

---

## Server Setup

1. Clone the repo on your server
2. Run `composer install` to install PHPMailer
3. Copy `config.example.php` to `config.php` and fill in your SMTP credentials
4. Point your domain to the project root

```bash
git clone https://github.com/abdullamusthafa-ops/mycarbonfootprint.git .
composer install
cp config.example.php config.php
# Edit config.php with your SMTP credentials
```

---

## File Structure

```
├── index.html              # Main calculator page
├── style.css               # All styles
├── mail.php                # Email report (PHP + PHPMailer)
├── config.example.php      # SMTP config template (copy to config.php)
├── config.php              # SMTP credentials — excluded from Git
├── screenshot_create.php   # Screenshot capture for email
├── assets/
│   └── CFC_2026_Methodology.pdf  # Official methodology document
├── images/                 # All images and icons
├── js/                     # JavaScript libraries
├── vendor/                 # Composer dependencies (excluded from Git)
└── composer.json
```

---

## Methodology

See `assets/CFC_2026_Methodology.pdf` for full emission factors, formulas, assumptions and limitations.

---

## Credits

Built by [Recall FZCO](https://recallearth.ae) for [mycarbonfootprint.ae](https://mycarbonfootprint.ae)
