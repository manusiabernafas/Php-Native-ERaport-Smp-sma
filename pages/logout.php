<?php
$_SESSION = [];
session_regenerate_id(true);
flash('Anda sudah keluar.');
redirect('login');
