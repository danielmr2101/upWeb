<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$authError = authError();
if ($authError !== null) {
    http_response_code(strpos($authError, 'Token') !== false || strpos($authError, 'token') !== false ? 401 : 403);
    echo json_encode(['ok' => false, 'error' => $authError], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureDirs();

    $action = (string)($_REQUEST['action'] ?? 'list');
    $project = (string)($_REQUEST['project'] ?? '');
    $id = (string)($_REQUEST['id'] ?? '');

    switch ($action) {
        case 'list':
            $data = array_map('projectInfo', listProjectNames());
            break;

        case 'start':
            $port = isset($_REQUEST['port']) && $_REQUEST['port'] !== '' ? (int)$_REQUEST['port'] : null;
            $data = startServer($project, $id, $port, (string)($_REQUEST['host'] ?? '127.0.0.1'));
            break;

        case 'stop':
            $data = ['stopped' => stopServer($project, $id)];
            break;

        case 'stop-all':
            $data = ['stopped' => stopAllServers()];
            break;

        case 'up':
            $data = upProject($project);
            break;

        case 'stop-project':
            $data = ['stopped' => stopProject($project)];
            break;

        case 'services':
            $data = servicesStatus();
            break;

        case 'custom-add':
            addCustomCommand(
                $project,
                (string)($_REQUEST['label'] ?? ''),
                (string)($_REQUEST['command'] ?? ''),
                (int)($_REQUEST['port'] ?? 8080)
            );
            $data = ['added' => true];
            break;

        case 'custom-remove':
            removeCustomCommand($project, (string)($_REQUEST['customId'] ?? ''));
            $data = ['removed' => true];
            break;

        case 'log':
            $data = ['log' => serverLog($project, $id, (int)($_REQUEST['lines'] ?? 80))];
            break;

        case 'stream':
            streamLog($project, $id, (int)($_REQUEST['from'] ?? 0));
            exit;

        case 'open':
            openTarget((string)($_REQUEST['target'] ?? 'url'), (string)($_REQUEST['value'] ?? ''));
            $data = ['opened' => true];
            break;

        case 'laragon':
            $data = ['action' => laragonControl((string)($_REQUEST['do'] ?? ''))];
            break;

        default:
            throw new RuntimeException('Accion desconocida');
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
