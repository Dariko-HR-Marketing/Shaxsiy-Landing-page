<?php
// B3: cheklov buzilishini matn emas, SQLSTATE/drayver kodi bo'yicha aniqlash (birlik testi).
require __DIR__ . '/lib.php';
libs();
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE t (a INTEGER UNIQUE, b INTEGER CHECK (b > 0))'); $db->exec('INSERT INTO t VALUES (1, 1)');
$catch = function (string $sql) use ($db): ?PDOException { try { $db->exec($sql); } catch (PDOException $e) { return $e; } return null; };
t_assert(pdo_is_constraint_violation($catch('INSERT INTO t VALUES (1, 1)')), 'UNIQUE -> cheklov');
t_assert(pdo_is_constraint_violation($catch('INSERT INTO t VALUES (2, -1)')), 'CHECK -> cheklov');
t_assert(!pdo_is_constraint_violation($catch('SELECT * FROM yoq_jadval')), 'sintaksis/jadval xatosi -> cheklov EMAS');
// Xabar matni boshqa tilda bo'lsa ham (lokalizatsiya) — kod bo'yicha aniqlanadi.
$fake = new PDOException('Нарушение ограничения уникальности'); $fake->errorInfo = ['23000', 19, 'Нарушение ограничения уникальности'];
t_assert(pdo_is_constraint_violation($fake), 'lokalizatsiyalangan matn -> baribir aniqlanadi');
$other = new PDOException('database is locked (UNIQUE so‘zi bor)'); $other->errorInfo = ['HY000', 5, 'database is locked UNIQUE'];
t_assert(!pdo_is_constraint_violation($other), 'matnda "UNIQUE" bo‘lsa ham qulf xatosi -> cheklov EMAS');
t_assert(!booking_slot_taken('2000-01-01', '09:00'), 'bo‘sh slot band emas');
t_done('cheklov kodi aniqlash');
