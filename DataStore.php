<?php

class Store {
    private string $file;
    private array $data;

    public function __construct(string $file = 'data.json') {
        $this->file = $file;
        if (!file_exists($file)) {
            file_put_contents($file, json_encode(["deals"=>[], "contacts"=>[]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        }
        $json = file_get_contents($file);
        $this->data = $json ? json_decode($json, true) : ["deals"=>[], "contacts"=>[]];
        if (!isset($this->data['deals']))    $this->data['deals'] = [];
        if (!isset($this->data['contacts'])) $this->data['contacts'] = [];
    }

    private function save(): void {
        file_put_contents($this->file, json_encode($this->data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }

    public function list(string $menu): array {
        return $this->data[$menu] ?? [];
    }

    public function findById(string $menu, int $id): ?array {
        foreach ($this->data[$menu] as $row) {
            if ((int)$row['id'] === $id) return $row;
        }
        return null;
    }

    private function nextId(string $menu): int {
        $max = 0;
        foreach ($this->data[$menu] as $row) $max = max($max, (int)$row['id']);
        return $max + 1;
    }

    public function createDeal(array $payload): array {

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Поле "Наименование" обязательно');

        $amount = isset($payload['amount']) ? (int)$payload['amount'] : 0;
        $contacts = isset($payload['contacts']) && is_array($payload['contacts'])
            ? array_values(array_unique(array_map('intval', $payload['contacts'])))
            : [];

        $new = [
            "id" => $this->nextId('deals'),
            "name" => $name,
            "amount" => $amount,
            "contacts" => $contacts
        ];
        $this->data['deals'][] = $new;

        foreach ($contacts as $cid) {
            $this->attachDealToContact($new['id'], $cid);
        }
        $this->save();
        return $new;
    }

    public function updateDeal(int $id, array $payload): array {
        $idx = null;
        foreach ($this->data['deals'] as $i => $row) if ((int)$row['id']===$id) { $idx=$i; break; }
        if ($idx===null) throw new RuntimeException('Сделка не найдена');

        $name = trim((string)($payload['name'] ?? $this->data['deals'][$idx]['name']));
        if ($name === '') throw new RuntimeException('Поле "Наименование" обязательно');

        $amount = isset($payload['amount']) ? (int)$payload['amount'] : (int)$this->data['deals'][$idx]['amount'];
        $oldContacts = $this->data['deals'][$idx]['contacts'];
        $newContacts = isset($payload['contacts']) && is_array($payload['contacts'])
            ? array_values(array_unique(array_map('intval', $payload['contacts'])))
            : $oldContacts;

        $this->data['deals'][$idx]['name'] = $name;
        $this->data['deals'][$idx]['amount'] = $amount;
        $this->data['deals'][$idx]['contacts'] = $newContacts;

        $removed = array_diff($oldContacts, $newContacts);
        $added   = array_diff($newContacts, $oldContacts);

        foreach ($removed as $cid) $this->detachDealFromContact($id, $cid);
        foreach ($added as $cid)   $this->attachDealToContact($id, $cid);

        $this->save();
        return $this->data['deals'][$idx];
    }

    public function deleteDeal(int $id): void {

        foreach ($this->data['contacts'] as &$c) {
            $c['deals'] = array_values(array_filter($c['deals'], fn($d)=> (int)$d !== $id));
        }
        unset($c);

        $this->data['deals'] = array_values(array_filter($this->data['deals'], fn($d)=> (int)$d['id'] !== $id));
        $this->save();
    }

    public function createContact(array $payload): array {

        $first = trim((string)($payload['first_name'] ?? ''));
        if ($first === '') throw new RuntimeException('Поле "Имя" обязательно');
        $last  = trim((string)($payload['last_name'] ?? ''));

        $deals = isset($payload['deals']) && is_array($payload['deals'])
            ? array_values(array_unique(array_map('intval', $payload['deals'])))
            : [];

        $new = [
            "id" => $this->nextId('contacts'),
            "first_name" => $first,
            "last_name"  => $last,
            "deals"      => $deals
        ];
        $this->data['contacts'][] = $new;

        foreach ($deals as $did) {
            $this->attachContactToDeal($new['id'], $did);
        }
        $this->save();
        return $new;
    }

    public function updateContact(int $id, array $payload): array {
        $idx = null;
        foreach ($this->data['contacts'] as $i => $row) if ((int)$row['id']===$id) { $idx=$i; break; }
        if ($idx===null) throw new RuntimeException('Контакт не найден');

        $first = trim((string)($payload['first_name'] ?? $this->data['contacts'][$idx]['first_name']));
        if ($first === '') throw new RuntimeException('Поле "Имя" обязательно');
        $last = trim((string)($payload['last_name'] ?? $this->data['contacts'][$idx]['last_name']));

        $oldDeals = $this->data['contacts'][$idx]['deals'];
        $newDeals = isset($payload['deals']) && is_array($payload['deals'])
            ? array_values(array_unique(array_map('intval', $payload['deals'])))
            : $oldDeals;

        $this->data['contacts'][$idx]['first_name'] = $first;
        $this->data['contacts'][$idx]['last_name']  = $last;
        $this->data['contacts'][$idx]['deals']      = $newDeals;

        $removed = array_diff($oldDeals, $newDeals);
        $added   = array_diff($newDeals, $oldDeals);

        foreach ($removed as $did) $this->detachContactFromDeal($id, $did);
        foreach ($added as $did)   $this->attachContactToDeal($id, $did);

        $this->save();
        return $this->data['contacts'][$idx];
    }

    public function deleteContact(int $id): void {

        foreach ($this->data['deals'] as &$d) {
            $d['contacts'] = array_values(array_filter($d['contacts'], fn($c)=> (int)$c !== $id));
        }
        unset($d);

        $this->data['contacts'] = array_values(array_filter($this->data['contacts'], fn($c)=> (int)$c['id'] !== $id));
        $this->save();
    }

    private function attachDealToContact(int $dealId, int $contactId): void {
        foreach ($this->data['contacts'] as &$c) {
            if ((int)$c['id'] === $contactId) {
                if (!in_array($dealId, $c['deals'])) $c['deals'][] = $dealId;
                break;
            }
        }
        unset($c);
    }
    private function detachDealFromContact(int $dealId, int $contactId): void {
        foreach ($this->data['contacts'] as &$c) {
            if ((int)$c['id'] === $contactId) {
                $c['deals'] = array_values(array_filter($c['deals'], fn($d)=> (int)$d !== $dealId));
                break;
            }
        }
        unset($c);
    }
    private function attachContactToDeal(int $contactId, int $dealId): void {
        foreach ($this->data['deals'] as &$d) {
            if ((int)$d['id'] === $dealId) {
                if (!in_array($contactId, $d['contacts'])) $d['contacts'][] = $contactId;
                break;
            }
        }
        unset($d);
    }
    private function detachContactFromDeal(int $contactId, int $dealId): void {
        foreach ($this->data['deals'] as &$d) {
            if ((int)$d['id'] === $dealId) {
                $d['contacts'] = array_values(array_filter($d['contacts'], fn($c)=> (int)$c !== $contactId));
                break;
            }
        }
        unset($d);
    }

    public function options(): array {
        return [
            "deals" => array_map(fn($d)=>["id"=>$d["id"], "name"=>$d["name"]], $this->data['deals']),
            "contacts" => array_map(fn($c)=>["id"=>$c["id"], "name"=>$c["first_name"]." ".$c["last_name"]], $this->data['contacts'])
        ];
    }
}
