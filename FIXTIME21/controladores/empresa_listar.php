<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Sesion.php';
require_once __DIR__ . '/../clases/EmpresaRepositorio.php';

Sesion::requiereLogin();

header('Content-Type: application/json; charset=utf-8');

$uid  = (int)($_SESSION['uid'] ?? 0);

try {
    $repo = new EmpresaRepositorio();
    $rows = $repo->listarEmpresasPorPersonaContactos($uid);

    echo json_encode([
        'ok'       => true,
        'empresas' => array_map(function(array $e){
            return [
                'id'           => (int)($e['id'] ?? 0),
                'razon_social' => (string)($e['razon_social'] ?? ''),
                'CUIT'         => (string)($e['CUIT'] ?? ''),
            ];
        }, $rows),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo obtener la lista de empresas.']);
}
