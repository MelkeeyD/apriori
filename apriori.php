<?php

class Apriori
{
    protected string $transactionField;
    protected string $productField;
    protected float $minSupport;
    protected float $minConf;

    protected int $countOrders;
    protected array $sets;
    protected array $rejectedSets;
    protected array $normalizeProducts;
    protected array $rules;

    /**
     * Apriori constructor.
     * @param $orderList
     * @param float $minSupport
     * @param float $minConf
     * @param string $transactionField
     * @param string $productField
     */
    public function __construct($orderList, float $minSupport, float $minConf, string $transactionField = 'transaction_id', string $productField = 'product')
    {
        $this->transactionField = $transactionField;
        $this->productField = $productField;
        $this->minConf = $minConf;
        $this->minSupport = $minSupport;

        $this->normalize($orderList);
        $this->generateOneElementSets();
        $this->generateSets();
        $this->rules = $this->calculateRules();
    }

    /**
     * @param array $orders
     */
    protected function normalize(array $orders)
    {
        $normalizeOrders = [];
        $this->normalizeProducts = [];
        foreach ($orders as $order) {
            $normalizeOrders[$order[$this->transactionField]][$order[$this->productField]] = true;
            $this->normalizeProducts[$order[$this->productField]][] = $order[$this->transactionField];
        }

        $this->countOrders = count($normalizeOrders);
    }

    /**
     * @return array
     */
    protected function generateOneElementSets(): array
    {
        $result = [];

        foreach ($this->normalizeProducts as $product => $orders) {
            $relSupport = count($orders) / $this->countOrders;
            if ($relSupport < $this->minSupport) {
                $this->rejectedSets[0][] = [$product];
                continue;
            }
            $result[] = [
                'products' => [$product],
                'support' => count($orders),
                'rel_support' => round($relSupport, 4)
            ];
        }

        $this->sets = [$result];
        return $result;
    }

    /**
     * @param array $set
     * @param int $k
     * @return bool
     */
    protected function checkRejected(array $set, int $k): bool
    {
        if ($k === 0) {
            return true;
        }

        foreach ($this->rejectedSets[$k] as $rejectedSet) {
            $intersect = array_intersect($rejectedSet, $set);
            if(!empty($intersect) && empty(array_diff($rejectedSet, $intersect))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $k
     * @return array
     */
    protected function generateNextSet(int $k): array
    {
        $result = [];

        $countSets = count($this->sets[$k]);
        for ($i = 0; $i < $countSets; $i++) {
            for ($j = $i + 1; $j < $countSets; $j++) {
                $newSet = array_unique(array_merge($this->sets[$k][$i]['products'], $this->sets[$k][$j]['products']));
                if (count($newSet) > $k + 2 && $this->sets[$k][$i]['rel_support'] +
                    $this->sets[$k][$j]['rel_support'] < $this->minSupport) {
                    continue;
                }

                if (!$this->checkRejected($newSet, $k)) {
                    $this->rejectedSets[$k + 1][] = $newSet;
                    continue;
                }

                $result[] = $newSet;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getSets(): array
    {
        return $this->sets;
    }

    /**
     * @param int $k
     */
    protected function generateSets($k = 0)
    {
        $nextSet = $this->generateNextSet($k);

        if (empty($nextSet)) {
            return;
        }

        foreach ($nextSet as $set) {
            $support = $this->getProductsSupport($set);
            $relSupport = $support / $this->countOrders;
            if ($relSupport < $this->minSupport) {
                $this->rejectedSets[$k + 1][] = $set;
                continue;
            }
            $this->sets[$k + 1][] = [
                'products' => $set,
                'support' => $support,
                'rel_support' => round($relSupport, 2)
            ];
        }

        $this->generateSets($k + 1);
    }

    /**
     * @param $products
     * @return int
     */
    protected function getProductsSupport($products): int
    {
        $orders = [];
        foreach ($products as $product) {
            $orders = empty($orders) ? $this->normalizeProducts[$product]
                : array_intersect($orders, $this->normalizeProducts[$product]);
        }

        return count(array_unique($orders));
    }

    /**
     * @return array
     */
    protected function calculateRules(): array
    {
        $rules = [];
        foreach ($this->sets as $sets) {
            foreach ($sets as $set) {
                $products = $set['products'];
                if (count($products) === 1) {
                    continue;
                }

                for ($i = 0; $i < count($products); $i++) {
                    $rule = $products;
                    $rightSide = $rule[$i];
                    unset($rule[$i]);
                    $leftSide = $rule;
                    $support = $this->getProductsSupport($leftSide);
                    if ($support != 0) {
                        $conf = $set["support"] / $support;
                        if ($conf > $this->minConf) {
                            $rules[] = [
                                'left' => $leftSide,
                                'right' => $rightSide,
                                'conf' => round($conf, 2)
                            ];
                        }
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
