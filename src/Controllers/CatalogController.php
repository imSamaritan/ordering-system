<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CatalogController
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    public function categories(Request $request, Response $response): Response
    {
        $categories = $this->productRepository->findActiveCategories();

        return ResponseHelper::json($response, [
            'categories' => $categories,
        ]);
    }

    public function products(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $category = isset($query['category']) ? (string) $query['category'] : null;
        $search = isset($query['q']) ? (string) $query['q'] : null;

        $products = $this->productRepository->findActiveProducts($category, $search);

        return ResponseHelper::json($response, [
            'products' => $products,
            'count' => count($products),
        ]);
    }
}
