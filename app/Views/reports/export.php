<!doctype html><html><head><meta charset="utf-8"><title><?= esc($title) ?></title></head><body><table><thead><tr>
<?php $columns = $rows === [] ? [] : array_keys($rows[0]); foreach ($columns as $column): ?><th><?= esc((string) $column) ?></th><?php endforeach ?>
</tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($columns as $column): ?><td><?= esc(is_array($row[$column]) ? json_encode($row[$column], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $row[$column]) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></body></html>
