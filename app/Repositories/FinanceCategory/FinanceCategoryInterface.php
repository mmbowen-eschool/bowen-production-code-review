<?php

namespace App\Repositories\FinanceCategory;

interface FinanceCategoryInterface
{
    public function all(array $columns = ['*'], array $relations = []);
    public function builder();
    public function findById(int $id, array $columns = ['*'], array $relations = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function deleteById(int $id);
}
