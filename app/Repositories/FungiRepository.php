<?php

namespace App\Repositories;

use App\Models\Fungi;

class FungiRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = Fungi::class;
    }

    public function find(int|string $key): Fungi
    {
        if (is_int($key)) {

            return $this->findId($key);
        } else if (is_string($key)) {

            return $this->findUuid($key);
        }
    }

    public function specieLike(string $specie, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('specie', $specie);
        } else {

            $this->ilike('specie', $specie);
        }
        return $this;
    }

    public function popularNameLike(string $name, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('popular_name', $name);
        } else {

            $this->ilike('popular_name', $name);
        }

        return $this;
    }

    public function kingdomLike(string $kingdom, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('kingdom', $kingdom);
        } else {

            $this->ilike('kingdom', $kingdom);
        }

        return $this;
    }

    public function phylumLike(string $phylum, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('phylum', $phylum);
        } else {

            $this->ilike('phylum', $phylum);
        }

        return $this;
    }

    public function classLike(string $class, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('class', $class);
        } else {

            $this->ilike('class', $class);
        }

        return $this;
    }

    public function orderLike(string $order, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('order', $order);
        } else {

            $this->ilike('order', $order);
        }

        return $this;
    }

    public function familyLike(string $family, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('family', $family);
        } else {

            $this->ilike('family', $family);
        }

        return $this;
    }

    public function genusLike(string $genus, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('genus', $genus);
        } else {

            $this->ilike('genus', $genus);
        }

        return $this;
    }

    public function scientificNameLike(string $name, string $boolOperator = 'and')
    {
        if ($boolOperator == 'or') {
            $this->orLike('scientific_name', $name);
        } else {

            $this->ilike('scientific_name', $name);
        }

        return $this;
    }

    public function getByStateAcronym(string $ac)
    {

        $this->where('state_acronym', $ac);

        return $this;
    }

    public function getByBiome(string $biome)
    {

        $this->where('biome', $biome);

        return $this;
    }

    public function getByBem(int $bem)
    {
        $this->where('bem', $bem);

        return $this;
    }
}
