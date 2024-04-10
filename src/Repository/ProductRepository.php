<?php

namespace Moontec\Repository;

use Illuminate\Support\Facades\DB;

class ProductRepository
{
    private int $itemsPaginate = 15;

    function __construct()
    {
    }

    function searchCatalogByName($nameSearch, $orderBy, $minPrice, $maxPrice)
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])

            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")

            ->orderBy("price", $orderBy)
            ->where("price", ">=", $minPrice, "and")
            ->where("price", "<=", $maxPrice)
            ->where("name", "like", "%" . $nameSearch . "%")
            ->where("active", "=", "1")

            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate($this->itemsPaginate);
    }

    function searchCatalogByCategory($orderBy, $minPrice, $maxPrice, $categoryId)
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")

            ->orderBy("price", $orderBy)
            ->where("category", "=", $categoryId)
            ->where("price", ">=", $minPrice, "and")
            ->where("price", "<=", $maxPrice)
            ->where("PS.stock", ">", "0")
            ->where("active", "=", 1)

            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate($this->itemsPaginate);
    }

    function searchCatalogByOferts($orderBy, $minPrice, $maxPrice)
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")

            ->orderBy("create_at", "desc")
            ->orderBy("price", $orderBy)
            ->where("price", ">=", $minPrice, "and")
            ->where("price", "<=", $maxPrice)
            ->whereNotNull("discont")
            ->where("active", "=", 1)

            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate($this->itemsPaginate);
    }

    function searchCatalogByReleases($orderBy, $minPrice, $maxPrice)
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")
            ->orderBy("create_at", "desc")
            ->orderBy("price", $orderBy)
            ->where("price", ">=", $minPrice, "and")
            ->where("price", "<=", $maxPrice)
            ->where("PS.stock", ">", "0")
            ->where("active", "=", 1)
            ->whereMonth("create_at", ">=", "MONTH(now()) - 3")

            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate($this->itemsPaginate);
    }

    function searchLandingByOferts()
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")
            ->orderBy("create_at", "desc")
            ->where("PS.stock", ">", "0")
            ->where("active", "=", 1)
            ->whereNotNull("discont")

            ->where("PV.variable_principal", "=", 1)
            ->where("PS.stock_principal", "=", 1)

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate(20);
    }

    function searchLandingByReleases()
    {
        return DB::table(table: 'products as P')
            ->select(["*", "PS.id as stock_cod"])
            ->join("products_variables as PV", "PV.prod_cod", "=", "P.id")
            ->join("products_stock as PS", "PS.variable_cod", "=", "PV.id")
            ->orderBy("create_at", "desc")
            ->where("PS.stock", ">", "0")
            ->where("active", "=", 1)
            ->whereMonth("create_at", ">=", "MONTH(now()) - 3")

            ->where("PV.variable_principal", "=", "1")
            ->where("PS.stock_principal", "=", "1")

            ->whereRaw("(SELECT COUNT(*) FROM products_stock AS PSB WHERE P.id = PSB.prod_cod AND PSB.stock > 0) > 0")
            ->paginate(20);
    }

    function searchListProducts($codList)
    {
        return  DB::table(table: 'products')
            ->whereIn("products.id", explode(",", $codList))
            ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
            ->where("PS.stock", ">", "0")
            ->get();
    }
}