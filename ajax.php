<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/DataStore.php';

$store = new Store();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function jsonOk($data)  { echo json_encode(["ok"=>true, "data"=>$data],  JSON_UNESCAPED_UNICODE); exit; }
function jsonErr($msg)  { http_response_code(400); echo json_encode(["ok"=>false,"error"=>$msg], JSON_UNESCAPED_UNICODE); exit; }

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'init': {
                $menu = $_GET['menu'] ?? 'contacts';
                if (!in_array($menu, ['deals','contacts'])) $menu = 'contacts';
                $list = $store->list($menu);
                $opts = $store->options();
                jsonOk(["menu"=>$menu, "list"=>$list, "options"=>$opts]);
            }
            case 'list': {
                $menu = $_GET['menu'] ?? '';
                if (!in_array($menu, ['deals','contacts'])) jsonErr('Неизвестное меню');
                $list = $store->list($menu);
                jsonOk($list);
            }
            case 'details': {
                $menu = $_GET['menu'] ?? '';
                $id   = (int)($_GET['id'] ?? 0);
                if (!in_array($menu, ['deals','contacts'])) jsonErr('Неизвестное меню');
                $item = $store->findById($menu, $id);
                if (!$item) jsonErr('Элемент не найден');

                if ($menu === 'deals') {
                    $contactsResolved = [];
                    foreach ($item['contacts'] as $cid) {
                        $c = $store->findById('contacts', (int)$cid);
                        if ($c) $contactsResolved[] = ["id"=>$c['id'], "name"=>$c['first_name']." ".$c['last_name']];
                    }
                    $item['_resolved'] = ["contacts"=>$contactsResolved];
                } else {
                    $dealsResolved = [];
                    foreach ($item['deals'] as $did) {
                        $d = $store->findById('deals', (int)$did);
                        if ($d) $dealsResolved[] = ["id"=>$d['id'], "name"=>$d['name']];
                    }
                    $item['_resolved'] = ["deals"=>$dealsResolved];
                }
                jsonOk($item);
            }
            case 'options': {
                jsonOk($store->options());
            }
            default:
                jsonErr('Unknown action');
        }
    }

    if ($method === 'POST') {
        $inp = json_decode(file_get_contents('php://input'), true) ?? [];
        switch ($action) {
            case 'create': {
                $menu = $inp['menu'] ?? '';
                if ($menu === 'deals')  $new = $store->createDeal($inp['payload'] ?? []);
                elseif ($menu === 'contacts') $new = $store->createContact($inp['payload'] ?? []);
                else jsonErr('Неизвестное меню');
                jsonOk($new);
            }
            case 'update': {
                $menu = $inp['menu'] ?? '';
                $id   = (int)($inp['id'] ?? 0);
                if ($menu === 'deals')  $upd = $store->updateDeal($id, $inp['payload'] ?? []);
                elseif ($menu === 'contacts') $upd = $store->updateContact($id, $inp['payload'] ?? []);
                else jsonErr('Неизвестное меню');
                jsonOk($upd);
            }
            case 'delete': {
                $menu = $inp['menu'] ?? '';
                $id   = (int)($inp['id'] ?? 0);
                if ($menu === 'deals')  $store->deleteDeal($id);
                elseif ($menu === 'contacts') $store->deleteContact($id);
                else jsonErr('Неизвестное меню');
                jsonOk(["deleted"=>true]);
            }
            default:
                jsonErr('Unknown action');
        }
    }

    jsonErr('Unsupported method');
} catch (Throwable $e) {
    jsonErr($e->getMessage());
}
