<?php
// ============================================
// SK Travel Planner — Password Generator
// ============================================

declare(strict_types=1);

$target = $_GET['target'] ?? 'new_password';

$allowedTargets = [
    'new_password',
    'upd_password'
];

if (!in_array($target, $allowedTargets, true)) {
    $target = 'new_password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Password Generator — SK Travel Planner</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
        <link rel="icon" href="../assets/icons/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        >

        <link rel="stylesheet" href="../style.css">
  

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #0D7377;
            --primary-dark: #095558;
            --accent: #E8912D;
            --accent-dark: #C75B2A;
            --dark: #1B2838;
            --text: #2D2D2D;
            --muted: #6B7280;
            --light: #F7F3ED;
            --border: #D1CBC0;
            --white: #FFFFFF;
            --danger: #DC3545;
            --success: #2E8B57;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 18px;
            font-family: "Source Sans 3", sans-serif;
            background:
                linear-gradient(
                    145deg,
                    #1B2838,
                    #095558 60%,
                    #0D7377
                );
        }

        .generator {
            width: 100%;
            max-width: 580px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 17px;
            background: white;
            box-shadow: 0 22px 65px rgba(0,0,0,.30);
        }

        .generator-header {
            padding: 25px 24px;
            color: white;
            text-align: center;
            background:
                linear-gradient(
                    135deg,
                    var(--dark),
                    var(--primary-dark)
                );
        }

        .generator-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            font-size: 24px;
            background:
                linear-gradient(
                    135deg,
                    var(--accent),
                    var(--accent-dark)
                );
        }

        .generator-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 23px;
            margin-bottom: 3px;
        }

        .generator-header p {
            color: rgba(255,255,255,.65);
            font-size: 13px;
        }

        .generator-body {
            padding: 25px;
        }

        .password-box {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .password-output {
            flex: 1;
            min-width: 0;
            padding: 13px 14px;
            border: 2px solid var(--border);
            border-radius: 9px;
            outline: none;
            color: var(--dark);
            background: var(--light);
            font-family: monospace;
            font-size: 15px;
            font-weight: 700;
        }

        .password-output:focus {
            border-color: var(--primary);
        }

        .copy-btn {
            width: 48px;
            border: 0;
            border-radius: 9px;
            color: white;
            background: var(--primary);
            cursor: pointer;
            font-size: 16px;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
        }

        .copy-btn.copied {
            background: var(--success);
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 7px;
            color: var(--dark);
            font-size: 14px;
            font-weight: 700;
        }

        .length-value {
            color: var(--primary);
        }

        input[type="range"] {
            width: 100%;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
        }

        .options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 9px;
            margin-top: 8px;
        }

        .option {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 11px;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            background: white;
            font-size: 13px;
            transition: .2s;
        }

        .option:hover {
            border-color: var(--primary);
            background: #f4fbfb;
        }

        .option input {
            accent-color: var(--primary);
        }

        .generate-btn {
            width: 100%;
            min-height: 48px;
            margin-top: 7px;
            border: 0;
            border-radius: 9px;
            color: white;
            background: var(--primary);
            cursor: pointer;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            transition: .2s;
        }

        .generate-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .use-btn {
            width: 100%;
            min-height: 46px;
            margin-top: 10px;
            border: 2px solid var(--accent);
            border-radius: 9px;
            color: var(--accent-dark);
            background: transparent;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            transition: .2s;
        }

        .use-btn:hover {
            color: white;
            background: var(--accent);
        }

        .security-note {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            padding: 13px;
            border-radius: 9px;
            color: #0c5460;
            background: #dff5f7;
            font-size: 12px;
            line-height: 1.5;
        }

        .security-note i {
            margin-top: 2px;
        }

        .strength {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 10px;
        }

        .strength-bar {
            flex: 1;
            height: 5px;
            border-radius: 4px;
            background: #EDE7DB;
        }

        .strength-text {
            min-width: 52px;
            text-align: right;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .footer {
            padding: 15px 20px 19px;
            border-top: 1px solid #eee7dc;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        .footer button {
            border: 0;
            color: var(--primary);
            background: none;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }

        @media (max-width: 500px) {

            body {
                padding: 8px;
            }

            .generator-body {
                padding: 20px 16px;
            }

            .generator-header {
                padding: 22px 16px;
            }

            .options {
                grid-template-columns: 1fr;
            }

            .password-box {
                flex-wrap: wrap;
            }

            .password-output {
                width: calc(100% - 56px);
            }
        }

    </style>
</head>

<body>

<div class="generator">

    <div class="generator-header">

        <div class="generator-icon">
            <i class="fas fa-key"></i>
        </div>

        <h1>Password Generator</h1>

        <p>SK Travel Planner</p>

    </div>


    <div class="generator-body">

        <div class="password-box">

            <input
                type="text"
                id="passwordOutput"
                class="password-output"
                readonly
                aria-label="Generated password"
            >

            <button
                type="button"
                class="copy-btn"
                id="copyBtn"
                onclick="copyPassword()"
                title="Copy password"
            >
                <i class="fas fa-copy"></i>
            </button>

        </div>


        <div class="field">

            <label>

                <span>Password Length</span>

                <span
                    class="length-value"
                    id="lengthValue"
                >
                    16
                </span>

            </label>

            <input
                type="range"
                id="length"
                min="8"
                max="64"
                value="16"
                oninput="updateLength()"
            >

            <div class="range-labels">
                <span>8</span>
                <span>64</span>
            </div>

        </div>


        <div class="field">

            <label>
                Character Options
            </label>

            <div class="options">

                <label class="option">
                    <input
                        type="checkbox"
                        id="uppercase"
                        checked
                    >
                    Uppercase A-Z
                </label>

                <label class="option">
                    <input
                        type="checkbox"
                        id="lowercase"
                        checked
                    >
                    Lowercase a-z
                </label>

                <label class="option">
                    <input
                        type="checkbox"
                        id="numbers"
                        checked
                    >
                    Numbers 0-9
                </label>

                <label class="option">
                    <input
                        type="checkbox"
                        id="symbols"
                        checked
                    >
                    Symbols
                </label>

            </div>

        </div>


        <div class="strength">

            <div
                class="strength-bar"
                id="strength1"
            ></div>

            <div
                class="strength-bar"
                id="strength2"
            ></div>

            <div
                class="strength-bar"
                id="strength3"
            ></div>

            <div
                class="strength-bar"
                id="strength4"
            ></div>

            <span
                class="strength-text"
                id="strengthText"
            >
                Strong
            </span>

        </div>


        <button
            type="button"
            class="generate-btn"
            onclick="generatePassword()"
        >
            <i class="fas fa-wand-magic-sparkles"></i>
            Generate Password
        </button>


        <button
            type="button"
            class="use-btn"
            onclick="usePassword()"
        >
            <i class="fas fa-check"></i>
            Use This Password
        </button>


        <div class="security-note">

            <i class="fas fa-shield-halved"></i>

            <div>
                Passwords are generated locally in your browser.
                The generated password is not sent to a server by this page.
            </div>

        </div>

    </div>


    <div class="footer">

        <button
            type="button"
            onclick="window.close()"
        >
            <i class="fas fa-xmark"></i>
            Close Generator
        </button>

    </div>

</div>


<script>

const targetField =
    <?= json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;


// --------------------------------------------
// Secure random character
// --------------------------------------------
function secureRandom(max) {

    const array =
        new Uint32Array(1);

    crypto.getRandomValues(array);

    return array[0] % max;
}


// --------------------------------------------
// Generate password
// --------------------------------------------
function generatePassword() {

    const length =
        parseInt(
            document.getElementById('length').value,
            10
        );

    const uppercase =
        document.getElementById('uppercase').checked;

    const lowercase =
        document.getElementById('lowercase').checked;

    const numbers =
        document.getElementById('numbers').checked;

    const symbols =
        document.getElementById('symbols').checked;


    let groups = [];

    if (uppercase) {
        groups.push('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    if (lowercase) {
        groups.push('abcdefghijklmnopqrstuvwxyz');
    }

    if (numbers) {
        groups.push('0123456789');
    }

    if (symbols) {
        groups.push('!@#$%^&*()-_=+[]{};:,.?');
    }


    if (groups.length === 0) {

        alert(
            'Please select at least one character type.'
        );

        return;
    }


    let password = '';


    // Guarantee at least one character
    // from each selected group.
    for (const group of groups) {

        password +=
            group.charAt(
                secureRandom(group.length)
            );
    }


    const allCharacters =
        groups.join('');


    while (password.length < length) {

        password +=
            allCharacters.charAt(
                secureRandom(allCharacters.length)
            );
    }


    // Fisher-Yates shuffle
    const chars =
        password.split('');

    for (
        let i = chars.length - 1;
        i > 0;
        i--
    ) {

        const j =
            secureRandom(i + 1);

        [
            chars[i],
            chars[j]
        ] = [
            chars[j],
            chars[i]
        ];
    }


    password =
        chars.join('');


    document.getElementById(
        'passwordOutput'
    ).value = password;


    updateStrength(password);
}


// --------------------------------------------
// Length
// --------------------------------------------
function updateLength() {

    const value =
        document.getElementById(
            'length'
        ).value;

    document.getElementById(
        'lengthValue'
    ).textContent = value;

    generatePassword();
}


// --------------------------------------------
// Strength
// --------------------------------------------
function updateStrength(password) {

    let score = 0;

    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    score = Math.min(score, 4);


    const labels = [
        'Weak',
        'Fair',
        'Good',
        'Strong'
    ];

    const colors = [
        '#DC3545',
        '#E8912D',
        '#D4A017',
        '#2E8B57'
    ];


    const color =
        colors[Math.max(score - 1, 0)];


    for (let i = 1; i <= 4; i++) {

        const bar =
            document.getElementById(
                'strength' + i
            );

        bar.style.background =
            i <= score
                ? color
                : '#EDE7DB';
    }


    document.getElementById(
        'strengthText'
    ).textContent =
        labels[score - 1] || 'Weak';


    document.getElementById(
        'strengthText'
    ).style.color = color;
}


// --------------------------------------------
// Copy
// --------------------------------------------
async function copyPassword() {

    const output =
        document.getElementById(
            'passwordOutput'
        );

    if (!output.value) return;


    try {

        await navigator.clipboard.writeText(
            output.value
        );

    } catch (error) {

        output.select();
        document.execCommand('copy');
    }


    const button =
        document.getElementById('copyBtn');

    const original =
        button.innerHTML;

    button.classList.add('copied');

    button.innerHTML =
        '<i class="fas fa-check"></i>';


    setTimeout(function() {

        button.classList.remove('copied');

        button.innerHTML =
            original;

    }, 1200);
}


// --------------------------------------------
// Send password back to opener
// --------------------------------------------
function usePassword() {

    const password =
        document.getElementById(
            'passwordOutput'
        ).value;


    if (!password) {

        generatePassword();

        return;
    }


    if (
        window.opener &&
        !window.opener.closed
    ) {

        window.opener.postMessage(
            {
                type: 'password',
                password: password,
                target: targetField
            },
            window.location.origin
        );

        window.close();

    } else {

        copyPassword();

        alert(
            'Password copied to your clipboard.'
        );
    }
}


// --------------------------------------------
// Generate on open
// --------------------------------------------
document.addEventListener(
    'DOMContentLoaded',
    function() {
        generatePassword();
    }
);

</script>

</body>
</html>