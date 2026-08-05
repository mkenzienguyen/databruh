<?php
declare(strict_types=1);

const DATABRUH_PASSWORD_MIN_LENGTH = 15;
const DATABRUH_PASSWORD_MAX_LENGTH = 128;

function passwordPolicyLength(string $password): int
{
    return mb_strlen($password, 'UTF-8');
}

function passwordPolicyLower(string $value): string
{
    return mb_strtolower($value, 'UTF-8');
}

function passwordPolicyNormalize(string $value): string
{
    $normalized = preg_replace(
        '/[^\p{L}\p{N}]+/u',
        '',
        passwordPolicyLower($value)
    );

    return $normalized ?? '';
}

function passwordPolicyCandidateVariants(string $password): array
{
    $lowercase = passwordPolicyLower($password);
    $canonical = strtr($lowercase, [
        '@' => 'a',
        '$' => 's',
        '0' => 'o',
    ]);

    return array_values(array_unique(array_filter([
        passwordPolicyNormalize($lowercase),
        passwordPolicyNormalize($canonical),
    ])));
}

function passwordPolicyCommonPasswords(): array
{
    return [
        '123456789012345',
        '123456789123456',
        '111111111111111',
        '000000000000000',
        'aaaaaaaaaaaaaaa',
        'passwordpassword',
        'password123456',
        'qwertyuiopasdfgh',
        'qwerty123456789',
        'abcdefghijklmnop',
        'abc123abc123abc',
        'iloveyouiloveyou',
        'iloveyouforever',
        'letmeinletmein',
        'welcome123456789',
        'adminadminadmin',
        'changemechangeme',
        'correcthorsebatterystaple',
        'thisisapassword',
        'mysecurepassword',
        'mystrongpassword',
        'trustnoonetrustnoone',
        'footballfootball',
        'dragonpassword',
    ];
}

function passwordPolicyCommonRoots(): array
{
    return [
        'password',
        'passw0rd',
        'qwerty',
        'letmein',
        'welcome',
        'admin',
        'administrator',
        'changeme',
        'iloveyou',
        'monkey',
        'dragon',
        'football',
        'trustnoone',
        'secret',
        'login',
    ];
}

function passwordPolicyIsPredictable(string $password): bool
{
    $normalizedPassword = passwordPolicyNormalize($password);

    if (
        $normalizedPassword !== ''
        && preg_match('/^(.{1,4})\1{3,}$/u', $normalizedPassword) === 1
    ) {
        return true;
    }

    foreach (passwordPolicyCandidateVariants($password) as $candidate) {
        if (in_array($candidate, passwordPolicyCommonPasswords(), true)) {
            return true;
        }

        foreach (passwordPolicyCommonRoots() as $root) {
            if ($candidate === $root) {
                return true;
            }

            if (str_starts_with($candidate, $root)) {
                $suffix = substr($candidate, strlen($root));

                if (preg_match('/^[0-9]{1,8}$/', $suffix) === 1) {
                    return true;
                }
            }
        }
    }

    return false;
}

function passwordPolicyContextTerms(string $fullName = '', string $email = ''): array
{
    $terms = ['databruh'];
    $fullName = trim($fullName);
    $email = trim($email);

    if ($fullName !== '') {
        $terms[] = $fullName;
        $nameParts = preg_split('/[^\p{L}\p{N}]+/u', $fullName) ?: [];

        foreach ($nameParts as $namePart) {
            if (passwordPolicyLength($namePart) >= 4) {
                $terms[] = $namePart;
            }
        }
    }

    if ($email !== '') {
        $terms[] = $email;
        $emailLocalPart = strstr($email, '@', true);

        if (is_string($emailLocalPart) && passwordPolicyLength($emailLocalPart) >= 4) {
            $terms[] = $emailLocalPart;
        }
    }

    $normalizedTerms = array_map('passwordPolicyNormalize', $terms);

    return array_values(array_unique(array_filter(
        $normalizedTerms,
        static fn (string $term): bool => passwordPolicyLength($term) >= 4
    )));
}

function passwordPolicyUsesContext(
    string $password,
    string $fullName = '',
    string $email = ''
): bool {
    foreach (passwordPolicyCandidateVariants($password) as $candidate) {
        foreach (passwordPolicyContextTerms($fullName, $email) as $term) {
            if (str_contains($candidate, $term)) {
                return true;
            }
        }
    }

    return false;
}

function passwordPolicyChecks(
    string $password,
    string $fullName = '',
    string $email = ''
): array {
    $length = passwordPolicyLength($password);
    $hasControlCharacter = preg_match('/[\x00-\x1F\x7F]/', $password) === 1;
    $canEvaluateContent = $length <= DATABRUH_PASSWORD_MAX_LENGTH
        && !$hasControlCharacter;
    $validLength = $length >= DATABRUH_PASSWORD_MIN_LENGTH
        && $canEvaluateContent;
    $mixedCase = $canEvaluateContent
        && preg_match('/\p{Ll}/u', $password) === 1
        && preg_match('/\p{Lu}/u', $password) === 1;
    $numberAndSymbol = $canEvaluateContent
        && preg_match('/\p{N}/u', $password) === 1
        && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
    $safeChoice = $validLength
        && $mixedCase
        && $numberAndSymbol
        && !passwordPolicyIsPredictable($password)
        && !passwordPolicyUsesContext($password, $fullName, $email);

    return [
        'validLength' => $validLength,
        'mixedCase' => $mixedCase,
        'numberAndSymbol' => $numberAndSymbol,
        'safeChoice' => $safeChoice,
    ];
}

function passwordPolicyErrors(
    string $password,
    string $fullName = '',
    string $email = ''
): array {
    $checks = passwordPolicyChecks($password, $fullName, $email);
    $errors = [];

    $length = passwordPolicyLength($password);
    $hasControlCharacter = preg_match('/[\x00-\x1F\x7F]/', $password) === 1;
    $canEvaluateContent = $length <= DATABRUH_PASSWORD_MAX_LENGTH
        && !$hasControlCharacter;

    if ($length < DATABRUH_PASSWORD_MIN_LENGTH) {
        $errors[] = sprintf(
            'Use at least %d characters. A unique passphrase is recommended.',
            DATABRUH_PASSWORD_MIN_LENGTH
        );
    }

    if ($length > DATABRUH_PASSWORD_MAX_LENGTH) {
        $errors[] = sprintf(
            'Use no more than %d characters.',
            DATABRUH_PASSWORD_MAX_LENGTH
        );
    }

    if ($hasControlCharacter) {
        $errors[] = 'Use printable characters only. Spaces are allowed.';
    }

    if ($canEvaluateContent && !$checks['mixedCase']) {
        $errors[] = 'Include at least one uppercase and one lowercase letter.';
    }

    if ($canEvaluateContent && !$checks['numberAndSymbol']) {
        $errors[] = 'Include at least one number and one symbol.';
    }

    if ($checks['validLength'] && $checks['mixedCase'] && $checks['numberAndSymbol']) {
        if (passwordPolicyIsPredictable($password)) {
            $errors[] = 'Choose a less predictable password; common passwords and simple variants are blocked.';
        }

        if (passwordPolicyUsesContext($password, $fullName, $email)) {
            $errors[] = 'Do not base the password on your name, email address, or Databruh.';
        }
    }

    return $errors;
}

function passwordPolicyClientConfig(
    string $fullName = '',
    string $email = ''
): array {
    return [
        'minLength' => DATABRUH_PASSWORD_MIN_LENGTH,
        'maxLength' => DATABRUH_PASSWORD_MAX_LENGTH,
        'commonPasswords' => passwordPolicyCommonPasswords(),
        'commonRoots' => passwordPolicyCommonRoots(),
        'contextValues' => passwordPolicyContextTerms($fullName, $email),
    ];
}

function passwordPolicyClientConfigJson(
    string $fullName = '',
    string $email = ''
): string {
    $json = json_encode(
        passwordPolicyClientConfig($fullName, $email),
        JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE
    );

    return is_string($json) ? $json : '{}';
}
