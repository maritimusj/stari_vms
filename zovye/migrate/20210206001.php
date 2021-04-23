<?php 
namespace zovye;

$query = User::query()->where("passport IS NOT NULL && passport <> ''");

foreach($query->findAll() as $entry) {
    Principal::update($entry);
}


