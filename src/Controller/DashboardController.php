<?php

namespace App\Controller;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use App\Repository\SupplyRequestRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(
        ProductRepository $productRepository,
        SupplierRepository $supplierRepository,
        SupplyRequestRepository $supplyRequestRepository,
        OrderRepository $orderRepository
    ): Response {

    
        $totalProducts = $productRepository->count(['isArchived' => false]);
        $totalSuppliers = $supplierRepository->count(['isArchived' => false]);

        $lowStock = (int) $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.quantity BETWEEN 1 AND 3')
            ->andWhere('p.isArchived = false')
            ->getQuery()
            ->getSingleScalarResult();

        $inStock = (int) $productRepository->createQueryBuilder('p')
        ->select('COUNT(p.id)')
        ->where('p.quantity > 3')
        ->andWhere('p.isArchived = false')
        ->getQuery()
        ->getSingleScalarResult();

        $outOfStock = (int) $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.quantity = 0')
            ->andWhere('p.isArchived = false')
            ->getQuery()
            ->getSingleScalarResult();

        $pendingRequests = $supplyRequestRepository->count(['status' => 'Pending']);
        $confirmedRequests = $supplyRequestRepository->count(['status' => 'Confirmed']);
        $rejectedRequests = $supplyRequestRepository->count(['status' => 'Rejected']);

        // ---------- 1) Stock Overview (product -> quantity) ----------
        $stockRows = $productRepository->createQueryBuilder('p')
            ->select('p.name AS name, p.quantity AS qty')
            ->andWhere('p.isArchived = false')
            ->orderBy('p.quantity', 'DESC')
            ->setMaxResults(12) // keep chart readable
            ->getQuery()->getResult();

        $stockLabels = array_map(fn($r) => $r['name'], $stockRows);
        $stockQty    = array_map(fn($r) => (int) $r['qty'], $stockRows);

        // ---------- 2) Category Distribution (category -> total qty) ----------
        $catRows = $productRepository->createQueryBuilder('p')
            ->select('p.category AS cat, COALESCE(SUM(p.quantity),0) AS qty')
            ->andWhere('p.isArchived = false')
            ->groupBy('p.category')
            ->orderBy('qty', 'DESC')
            ->getQuery()->getResult();

        $catLabels = array_map(fn($r) => $r['cat'] ?? 'Other', $catRows);
        $catQty    = array_map(fn($r) => (int) $r['qty'], $catRows);

        // ---------- 3) Suppliers Overview (supplier -> supply requests count) ----------
        // “Supplies performed” = how many requests were made per supplier (you can later filter confirmed only)
        $supRows = $supplyRequestRepository->createQueryBuilder('r')
            ->select('s.name AS supplier, COUNT(r.id) AS cnt')
            ->leftJoin('r.supplier', 's')
            ->where('s.isArchived = false')
            ->groupBy('s.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        $supLabels = array_map(fn($r) => $r['supplier'] ?? 'Unknown', $supRows);
        $supCnt    = array_map(fn($r) => (int) $r['cnt'], $supRows);

        // ---------- 4) Orders Overview (last 7 days: orders count + revenue) ----------
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-6 days');

        $dayLabels = [];
        $ordersPerDay = array_fill(0, 7, 0);
        $revenuePerDay = array_fill(0, 7, 0.0);

        for ($i = 0; $i < 7; $i++) {
            $dayLabels[] = $start->modify("+$i days")->format('D');
        }

        $orders = $orderRepository->createQueryBuilder('o')
            ->select('o.createdAt, o.totalAmount')
            ->andWhere('o.createdAt >= :start')
            ->setParameter('start', $start->setTime(0, 0))
            ->getQuery()
            ->getArrayResult();

        // Build map for 7 days
        $map = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->modify("+$i days")->format('Y-m-d');
            $map[$d] = ['cnt' => 0, 'sum' => 0.0];
        }

        foreach ($orders as $o) {
            // createdAt comes as string sometimes, normalize
            $dt = $o['createdAt'] instanceof \DateTimeInterface
                ? $o['createdAt']
                : new \DateTimeImmutable($o['createdAt']);

            $dayKey = $dt->format('Y-m-d');

            if (isset($map[$dayKey])) {
                $map[$dayKey]['cnt']++;
                $map[$dayKey]['sum'] += (float) $o['totalAmount'];
            }
        }

        // Fill arrays in order
        for ($i = 0; $i < 7; $i++) {
            $d = $start->modify("+$i days")->format('Y-m-d');
            $ordersPerDay[$i] = $map[$d]['cnt'];
            $revenuePerDay[$i] = $map[$d]['sum'];
        }

        $revenueLast7 = array_sum($revenuePerDay);

        // previous 7 days range
        $prevStart = $start->modify('-7 days');            // 7 days before start
        $prevEnd = $start->modify('-1 second');           // just before start

        $prevOrders = $orderRepository->createQueryBuilder('o')
            ->select('o.createdAt, o.totalAmount')
            ->andWhere('o.createdAt >= :s')
            ->andWhere('o.createdAt <= :e')
            ->setParameter('s', $prevStart->setTime(0,0))
            ->setParameter('e', $prevEnd)
            ->getQuery()
            ->getArrayResult();

        $revenuePrev7 = 0.0;
        foreach ($prevOrders as $o) {
            $revenuePrev7 += (float) $o['totalAmount'];
        }

        $growthPercent = null; // to handle division by 0
        if ($revenuePrev7 > 0) {
            $growthPercent = (($revenueLast7 - $revenuePrev7) / $revenuePrev7) * 100;
        }

        // ---------- 5) Recent Activities (latest supply requests + orders) ----------
        $recentSupply = $supplyRequestRepository->createQueryBuilder('r')
            ->leftJoin('r.product', 'p')->addSelect('p')
            ->leftJoin('r.supplier', 's')->addSelect('s')
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();

        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();


        return $this->render('dashboard/index.html.twig', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'products' => $totalProducts,
                'suppliers' => $totalSuppliers,
                'lowStock' => $lowStock,
                'inStock' => $inStock,
                'outOfStock' => $outOfStock,
                'pendingRequests' => $pendingRequests,
                'confirmedRequests' => $confirmedRequests,
                'rejectedRequests' => $rejectedRequests,
            ],

            // charts data
            'charts' => [
                'stock' => ['labels' => $stockLabels, 'values' => $stockQty],
                'categories' => ['labels' => $catLabels, 'values' => $catQty],
                'suppliers' => ['labels' => $supLabels, 'values' => $supCnt],
                'orders' => [
                    'labels' => $dayLabels,
                    'orders' => $ordersPerDay,
                    'revenue' => $revenuePerDay,
                ],
            ],
            // recent activities
            'recentSupply' => $recentSupply,
            'recentOrders' => $recentOrders,

            'finance' => [
                'last7' => $revenueLast7,
                'prev7' => $revenuePrev7,
                'growthPercent' => $growthPercent,
            ],
        ]);
    }
}
