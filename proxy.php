<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$input = json_decode(file_get_contents('php://input'), true);

$nome     = $input['customer_name']  ?? 'Cliente';
$telefone = $input['customer_phone'] ?? '11999999999';
$valor    = floatval($input['sale_amount'] ?? 0);

// Validações
if ($valor < 10) {
    http_response_code(400);
    echo json_encode(['message' => 'Valor mínimo é R$10,00']);
    exit;
}

if ($valor > 2200) {
    http_response_code(400);
    echo json_encode(['message' => 'Valor máximo permitido é R$2.200,00']);
    exit;
}

$valorCentavos = intval(round($valor * 100));

// API Key via variável de ambiente (seguro para repositório público)
$API_KEY = getenv('GHOSTSPAYS_API_KEY');

// Gera e-mail interno a partir do nome
$emailSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $nome));
if (empty($emailSlug)) $emailSlug = 'doador';
$emailInterno = $emailSlug . '@techpro.com.br';

// Gera CPF válido automaticamente
function gerarCpf() {
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = rand(0, 9);
    $s1 = 0;
    for ($i = 0; $i < 9; $i++) $s1 += $n[$i] * (10 - $i);
    $d1 = ($s1 * 10) % 11;
    if ($d1 >= 10) $d1 = 0;
    $n[] = $d1;
    $s2 = 0;
    for ($i = 0; $i < 10; $i++) $s2 += $n[$i] * (11 - $i);
    $d2 = ($s2 * 10) % 11;
    if ($d2 >= 10) $d2 = 0;
    $n[] = $d2;
    return implode('', $n);
}
$cpf = gerarCpf();

// Payload conforme documentação oficial GhostsPays
$payload = [
    'paymentMethod' => 'PIX',
    'amount'        => $valorCentavos,
    'description'   => 'Licenca de Software - Plano Pro',
    'customer' => [
        'name'  => $nome,
        'email' => $emailInterno,
        'phone' => $telefone,
        'document' => [
            'number' => $cpf,
            'type'   => 'CPF',
        ],
    ],
    'items' => [[
        'title'      => 'Plano Pro - Assistente de IA',
        'unitPrice'  => $valorCentavos,
        'quantity'   => 1,
    ]],
    'pix' => [
        'expiresInDays' => 1,
    ],
];

$ch = curl_init('https://api.ghostspaysv2.com/functions/v1/transactions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);
// Basic Auth: API Key como username, senha vazia
curl_setopt($ch, CURLOPT_USERPWD, $API_KEY . ':');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro cURL: ' . $curlError]);
    exit;
}

http_response_code($httpCode);
echo $response;