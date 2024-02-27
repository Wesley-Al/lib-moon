<?php

namespace Moontec\Repository;

use Illuminate\Support\Facades\DB;

class ProductRepository
{
    private int $itemsPaginate = 15;

    function __construct() 
    {}

    function searchCatalogByName($nameSearch, $orderBy, $minPrice, $maxPrice) {
        return DB::table(table: 'products')
                ->orderBy("price", $orderBy)                
                ->where("price", ">=", $minPrice, "and")
                ->where("price", "<=", $maxPrice)
                ->where("name", "like", "%" . $nameSearch . "%")
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->where("active", "=", "1")
                ->paginate($this -> itemsPaginate);
    }

    function searchCatalogByCategory($orderBy, $minPrice, $maxPrice, $categoryId) {
        return DB::table(table: 'products')
                ->orderBy("price", $orderBy)
                ->where("category", "=", $categoryId)
                ->where("price", ">=", $minPrice, "and")
                ->where("price", "<=", $maxPrice)                
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->where("active", "=", "1")
                ->paginate($this -> itemsPaginate);
    }

    function searchCatalogByOferts($orderBy, $minPrice, $maxPrice) {
        return DB::table(table: 'products')
                ->orderBy("create_at", "desc")                                                                   
                ->orderBy("price", $orderBy)                     
                ->where("price", ">=", $minPrice, "and")
                ->where("price", "<=", $maxPrice)                
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->whereNotNull("discont")    
                ->where("active", "=", "1")                          
                ->paginate($this -> itemsPaginate);        
    }

    function searchCatalogByReleases($orderBy, $minPrice, $maxPrice) {
        return DB::table(table: 'products')
                ->orderBy("create_at", "desc")                                                                   
                ->orderBy("price", $orderBy)                     
                ->where("price", ">=", $minPrice, "and")
                ->where("price", "<=", $maxPrice)                
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->where("active", "=", "1")
                ->whereMonth("create_at", ">=", "MONTH(now()) - 3")
                ->paginate($this -> itemsPaginate);   
    }

    function searchLandingByOferts() {
        return DB::table(table: 'products')
                ->orderBy("create_at", "desc")                                        
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->where("active", "=", "1")
                ->whereNotNull("discont")                              
                ->paginate(20);
    }

    function searchLandingByReleases() {
        return DB::table(table: 'products')
                ->orderBy("create_at", "desc")                                
                ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
                ->where("PS.stock", ">", "0")
                ->where("active", "=", "1")
                ->whereMonth("create_at", ">=", "MONTH(now()) - 3")                
                ->paginate(20);
    }

    function searchListProducts($codList) {
        return  DB::table(table: 'products')
            ->whereIn("products.id", explode(",", $codList))
            ->join("products_stock as PS", "products.id", "=", "PS.prod_cod")
            ->where("PS.stock", ">", "0")
            ->get();
    }
}