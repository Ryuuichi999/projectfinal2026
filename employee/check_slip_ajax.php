<?php
require '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_POST['doc_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$doc_id = $_POST['doc_id'];

// Fetch file path
$stmt = $conn->prepare("SELECT file_path FROM sign_documents WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Document not found']);
    exit;
}

$row = $result->fetch_assoc();
$filePath = "../" . $row['file_path']; // Adjust path relative to this script

if (!file_exists($filePath)) {
    echo json_encode(['status' => 'error', 'message' => 'File not found on server']);
    exit;
}

// Token
$token = '1a4e92a3-11d0-400e-9079-aa374779682a';

// Call API
function checkSlip($filePath, $token)
{
    $url = 'https://api.thunder.in.th/v1/verify';
    $cfile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));
    $data = ['file' => $cfile];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($response, true);
        if (isset($json['data']['transRef'])) {
            $sender = $json['data']['sender']['account']['name']['th'] ?? $json['data']['sender']['account']['name']['en'] ?? 'Unknown';
            $receiver = $json['data']['receiver']['account']['name']['th'] ?? $json['data']['receiver']['account']['name']['en'] ?? 'Unknown';
            $bank = $json['data']['sender']['bank']['name'] ?? 'Unknown Bank';

            return [
                'status' => 'success',
                'transRef' => $json['data']['transRef'],
                'amount' => $json['data']['amount']['amount'],
                'sender' => $sender,
                'receiver' => $receiver,
                'bank' => $bank,
                'date' => $json['data']['date']
            ];
        } else {
            return ['status' => 'error', 'message' => 'API returned no data'];
        }
    } else {
        $json = json_decode($response, true);
        $msg = $json['message'] ?? 'Unknown API Error';
        return ['status' => 'error', 'message' => "($httpCode) $msg"];
    }
}

$result = checkSlip($filePath, $token);
echo json_encode($result);
?>