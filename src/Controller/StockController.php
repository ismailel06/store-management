<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\SupplyRequest;
use App\Repository\ProductRepository;
use App\Repository\SupplyRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StockController extends AbstractController
{
    #[Route('/stock', name: 'app_stock_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository, SupplyRequestRepository $supplyRequestRepository): Response
    {
        $products = $productRepository->findActive();

        // latest supply request status per product
        $latestStatusByProductId = [];
        foreach ($products as $p) {
            $latest = $supplyRequestRepository->findOneBy(
                ['product' => $p],
                ['createdAt' => 'DESC']
            );
            $latestStatusByProductId[$p->getId()] = $latest?->getStatus();
        }

        $conn = $supplyRequestRepository->getEntityManager()->getConnection();

        $rows = $conn->fetchAllAssociative("
            SELECT status, COUNT(*) AS c
            FROM supply_request
            GROUP BY status
        ");

        $counts = ['Pending' => 0, 'Confirmed' => 0, 'Rejected' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }

        return $this->render('stock/index.html.twig', [
            'pageTitle' => 'Stock',
            'products' => $products,
            'counts' => $counts,
            'latestStatusByProductId' => $latestStatusByProductId,
        ]);
    }

    #[Route('/stock/{id}/request', name: 'app_stock_request', methods: ['POST'])]
    public function requestSupply(
        Product $product,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $qty = (int) $request->request->get('requestedQty', 0);

        if ($qty < 1) {
            $this->addFlash('danger', 'Quantity must be at least 1.');
            return $this->redirectToRoute('app_stock_index');
        }

        $sr = new SupplyRequest();
        $sr->setProduct($product);
        $sr->setSupplier($product->getSupplier());
        $sr->setRequestedQty($qty);
        // status defaults to Pending in constructor

        $em->persist($sr);
        $em->flush();

        $this->addFlash('success', 'Supply request sent successfully (Pending).');
        return $this->redirectToRoute('app_stock_index');
    }
}
