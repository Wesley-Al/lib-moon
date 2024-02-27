<?php

namespace Moontec\Services;

use Moontec\Repository\ProductRepository;
use Moontec\Utils\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductServices
{
    public function __construct(protected ProductRepository $productRepository)
    {
    }

    public function getProductsCatalog($orderBy, $type, $nameSearch, $minPrice, $maxPrice, $categoryId, $maxDefault)
    {
        $listProducts = null;
        $badgeFilters = array();

        $queryUri = "&orderBy=" . $orderBy
            . "&min=" . $minPrice
            . "&max=" . $maxPrice
            . "&search=" . $nameSearch
            . "&type=" . $type
            . "&category=" . $categoryId;

        $category = $this->getCategory($type, $categoryId);

        $listProducts = $this->getListProducts($nameSearch, $orderBy, $minPrice, $maxPrice, $categoryId, $type);

        $pagination = (object) [
            "currentPage" => $listProducts->currentPage(),
            "currentUrl" => $listProducts->url($listProducts->currentPage()) . $queryUri,
            "currentUrlWithOrderBy" => $listProducts->url($listProducts->currentPage())
                . "&orderBy=" . $orderBy
                . "&search=" . $nameSearch
                . "&type=" . $type
                . "&category=" . $categoryId,

            "totalPage" => $listProducts->lastPage(),
            "nextPage" => $listProducts->nextPageUrl() . $queryUri,
            "previusPage" => $listProducts->previousPageUrl() . $queryUri,
            "orderBy" => $orderBy
        ];

        if ($maxPrice != $maxDefault || $minPrice != "0") {
            array_push($badgeFilters, (object) [
                "url" => $listProducts->url($listProducts->currentPage())
                    . "&orderBy=" . $orderBy
                    . "&search=" . $nameSearch
                    . "&type=" . $type
                    . "&category=" . $categoryId,

                "name" => "R$ " . $minPrice . " até R$" . $maxPrice
            ]);
        }

        if ($nameSearch != "") {
            array_push($badgeFilters, (object) [
                "url" => $listProducts->url($listProducts->currentPage())
                    . "&orderBy=" . $orderBy
                    . "&min=" . $minPrice
                    . "&max=" . $maxPrice
                    . "&type=" . $type
                    . "&category=" . $categoryId,

                "name" => $nameSearch
            ]);

            $category = (object)[
                "id" => 99,
                "name" => "Resultados da pesquisa"
            ];
        }

        return [
            "products" => $listProducts->items(),
            "pagination" => $pagination,
            "badgeFilters" => $badgeFilters,
            "category" => $category
        ];
    }

    public function getProductsLanding()
    {
        $releases = $this->productRepository->searchLandingByReleases();
        $oferts = $this->productRepository->searchLandingByOferts();                

        return (object)[ 
            "releases" => (object)[
                "items" => $releases->items(),
                "hasMore" => $releases->hasMorePages()
            ],
            "oferts" => (object)[
                "items" => $oferts->items(),
                "hasMore" => $oferts->hasMorePages()
            ]            
        ];
    }

    //ADMIN
    public function updateProductImages($imageRemove, $productId, $principalImage, $files)
    {
        $fileNames = [];
        $fileNamesUpload = [];
        $size = 5;        

        DB::beginTransaction();

        try {            
            $product = DB::table('products')
                ->select('img_list')
                ->where('id', '=', $productId)                
                ->first();

            $productImages = $product->img_list != null && $product->img_list != '' ? explode(',', $product->img_list) : [];
            
            if (sizeof($imageRemove) > 0) {
                $this->removeFiles($imageRemove);                

                if (sizeof($imageRemove) != $size) {
                    $joinRemove = join(",", $imageRemove);

                    foreach ($productImages as $img) {
                        if (!Str::contains($joinRemove, $img)) {
                            array_push($fileNames, $img);
                        }
                    }
                }
            } else if (sizeof($productImages) > 0) {
                foreach ($productImages as $img) {
                    array_push($fileNames, $img);
                }
            }

            if (sizeof($files) > 0) {                

                if ((sizeof($files) + sizeof($fileNames)) > $size) {
                    throw new \Exception('Não é permitido salvar mais que ' . $size . ' arquivos.');
                }

                foreach ($files as $file) {
                    $nameFile = $this->uploadImage($file, $productId);

                    if($file->getClientOriginalName() == $principalImage) {
                        $principalImage = $nameFile;
                    }

                    array_push($fileNames, $nameFile);
                    array_push($fileNamesUpload, $nameFile);
                }
            }

            $fileNames = $this->rearrangeImages($fileNames, $principalImage);

            DB::table('products')
                ->where('id', '=', $productId)
                ->update(['img_list' => join(',', $fileNames)]);

            DB::commit();

        } catch (\Exception $e) {            
            DB::rollBack();
            $this->removeFiles($fileNamesUpload);

            throw new \Exception($e->getMessage());            
        }
    }

    //PRIVATE FUNCTIONS
    private function getCategory($type, $categoryId)
    {
        $category = (object)[
            "id" => null,
            "name" => null
        ];

        if ($type != "none") {
            switch ($type) {
                case "releases":
                    $category->name = "Lançamentos";
                    break;
                default:
                    $category->name = "Ofertas";
                    break;
            }
        } else {
            $dataCategory = Utils::getListCategory();

            foreach ($dataCategory as $value) {
                if ($value->id == $categoryId) {
                    $category = $value;
                    break;
                }
            }
        }

        return $category;
    }

    private function getListProducts($nameSearch, $orderBy, $minPrice, $maxPrice, $categoryId, $type)
    {
        if ($type != "none") {
            switch ($type) {
                case "releases":
                    $listProducts = $this->productRepository->searchCatalogByReleases($orderBy, $minPrice, $maxPrice);
                    break;
                default:
                    $listProducts = $this->productRepository->searchCatalogByOferts($orderBy, $minPrice, $maxPrice);
                    break;
            }
        } else if ($nameSearch != "") {
            $listProducts =  $this->productRepository->searchCatalogByName($nameSearch, $orderBy, $minPrice, $maxPrice);
        } else {
            $listProducts =  $this->productRepository->searchCatalogByCategory($orderBy, $minPrice, $maxPrice, $categoryId);
        }

        return $listProducts;
    }       
     
     private function uploadImage($file, $productId)
     {
         $dirFile = env("PATH_IMAGES_PRODUCT");
         $fileName = $dirFile . "/" . $productId . "_". Str::random(9) . ".jpg";
 
         $data = fopen($fileName, 'wb');
         fwrite($data, file_get_contents($file));
         fclose($data);
 
         return $fileName;
     }
 
     private function removeFiles($fileNames)
     {
         try {
             foreach ($fileNames as $fileName) {
                 if (file_exists($fileName)) {
                     unlink($fileName);
                 }
             }
         } catch (\Exception $e) {
             Log::channel("exception")->error("ProductsController.removeFiles: " . $e->getMessage());
         }
     }
 
     private function rearrangeImages($nameImages, $principalImg)
     {
         $response = [];
         $arrayPrincipalImage = array_filter($nameImages, function ($name) use ($principalImg) {
             return Str::contains($name, $principalImg);
         });
 
         //SALVA PRIMEIRO A IMAGEM PRINCIPAL
         array_push($response, array_shift($arrayPrincipalImage));
 
         //SALVA AS DEMAIS IMAGENS
         $response = array_merge($response, array_filter($nameImages, function ($name) use ($principalImg) {
             return !Str::contains($name, $principalImg);
         }));
 
         return $response;
     }
}
