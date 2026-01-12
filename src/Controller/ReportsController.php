<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplyRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportsController extends AbstractController
{
    #[Route('/reports', name: 'app_reports_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        SupplyRequestRepository $supplyRequestRepository,
        OrderRepository $orderRepository
    ): Response {
        $lowStock = $productRepository->createQueryBuilder('p')
            ->andWhere('p.quantity BETWEEN 1 AND 3')
            ->orderBy('p.quantity', 'ASC')
            ->getQuery()->getResult();

        $outOfStock = $productRepository->createQueryBuilder('p')
            ->andWhere('p.quantity = 0')
            ->orderBy('p.id', 'DESC')
            ->getQuery()->getResult();

        // “Activities last 7 days” (simple: supply requests + orders)
        $since = new \DateTimeImmutable('-7 days');

        $recentSupply = $supplyRequestRepository->createQueryBuilder('r')
            ->andWhere('r.createdAt >= :since OR r.updatedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        return $this->render('reports/index.html.twig', [
            'pageTitle' => 'Reports',
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'recentSupply' => $recentSupply,
            'recentOrders' => $recentOrders,
        ]);
    }

    #[Route('/reports/stock.csv', name: 'app_reports_export_stock', methods: ['GET'])]
    public function exportStock(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();
        
        $csvContent = "Product Name,Category,Supplier,Quantity,Price\n";

        foreach ($products as $product) {
            $csvContent .= "\"{$product->getName()}\",\"{$product->getCategory()}\",\"{$product->getSupplier()->getName()}\",{$product->getQuantity()},{$product->getPrice()}\n";
        }

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock_report.csv"',
        ]);
    }

    #[Route('/reports/stock.txt', name: 'app_reports_export_stock_txt', methods: ['GET'])]
    public function exportStockTxt(ProductRepository $productRepository): Response
    {
        $products = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.supplier', 's')->addSelect('s')
            ->andWhere('p.isArchived = false')
            ->orderBy('p.name', 'ASC')
            ->getQuery()->getResult();

        $txt = "STOCK REPORT\n";
        $txt .= "Generated: ".(new \DateTimeImmutable())->format('Y-m-d H:i')."\n\n";
        $txt .= str_pad("Name", 25).str_pad("Category", 15).str_pad("Supplier", 20).str_pad("Qty", 6)."Price\n";
        $txt .= str_repeat("-", 80)."\n";

        foreach ($products as $p) {
            $supplierName = $p->getSupplier() ? $p->getSupplier()->getName() : '-';
            $txt .= str_pad((string) $p->getName(), 25)
                . str_pad((string) $p->getCategory(), 15)
                . str_pad($supplierName, 20)
                . str_pad((string) $p->getQuantity(), 6)
                . (string) $p->getPrice()
                . "\n";
        }

        return new Response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stock_report.txt"',
        ]);
    }

    #[Route('/reports/stock.pdf', name: 'app_reports_export_stock_pdf', methods: ['GET'])]
    public function exportStockPdf(ProductRepository $productRepository): Response
    {
        $lowStock = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.supplier', 's')->addSelect('s')
            ->andWhere('p.isArchived = false')
            ->andWhere('p.quantity BETWEEN 1 AND 3')
            ->orderBy('p.quantity', 'ASC')
            ->getQuery()->getResult();

        $outOfStock = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.supplier', 's')->addSelect('s')
            ->andWhere('p.isArchived = false')
            ->andWhere('p.quantity = 0')
            ->orderBy('p.name', 'ASC')
            ->getQuery()->getResult();

        $html = $this->renderView('reports/pdf/stock.html.twig', [
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="stock_report.pdf"',
        ]);
    }

    #[Route('/reports/orders.txt', name: 'app_reports_export_orders_txt', methods: ['GET'])]
    public function exportOrdersTxt(OrderRepository $orderRepository): Response
    {
        $since = new \DateTimeImmutable('-7 days');

        $orders = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()->getResult();

        $txt = "ORDERS REPORT (Last 7 days)\n";
        $txt .= "Generated: ".(new \DateTimeImmutable())->format('Y-m-d H:i')."\n\n";
        $txt .= str_pad("ID", 8).str_pad("Status", 12).str_pad("Total", 12)."Placed At\n";
        $txt .= str_repeat("-", 60)."\n";

        foreach ($orders as $o) {
            $txt .= str_pad('#'.$o->getId(), 8)
                . str_pad((string) $o->getStatus(), 12)
                . str_pad((string) $o->getTotalAmount(), 12)
                . ($o->getCreatedAt()?->format('Y-m-d H:i') ?? '-')
                . "\n";
        }

        return new Response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orders_report_last7days.txt"',
        ]);
    }

    #[Route('/reports/orders.pdf', name: 'app_reports_export_orders_pdf', methods: ['GET'])]
    public function exportOrdersPdf(OrderRepository $orderRepository): Response
    {
        $since = new \DateTimeImmutable('-7 days');

        $orders = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()->getResult();

        $html = $this->renderView('reports/pdf/orders.html.twig', [
            'orders' => $orders,
            'generatedAt' => new \DateTimeImmutable(),
            'since' => $since,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="orders_report_last7days.pdf"',
        ]);
    }

    #[Route('/reports/activities.txt', name: 'app_reports_export_activities_txt', methods: ['GET'])]
    public function exportActivitiesTxt(SupplyRequestRepository $supplyRequestRepository, OrderRepository $orderRepository): Response
    {
        $since = new \DateTimeImmutable('-7 days');

        $recentSupply = $supplyRequestRepository->createQueryBuilder('r')
            ->leftJoin('r.product', 'p')->addSelect('p')
            ->leftJoin('r.supplier', 's')->addSelect('s')
            ->andWhere('r.createdAt >= :since OR r.updatedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('r.updatedAt', 'DESC')
            ->getQuery()->getResult();

        $txt = "ACTIVITIES REPORT (Last 7 days)\n";
        $txt .= "Generated: ".(new \DateTimeImmutable())->format('Y-m-d H:i')."\n\n";

        $txt .= "SUPPLY REQUESTS\n";
        $txt .= str_repeat("-", 60)."\n";
        foreach ($recentSupply as $r) {
            $txt .= sprintf(
                "[%s] %s • %s • %s (updated %s)\n",
                $r->getStatus(),
                $r->getProduct()?->getName() ?? '-',
                $r->getSupplier()?->getName() ?? '-',
                $r->getCreatedAt()?->format('Y-m-d H:i') ?? '-',
                $r->getUpdatedAt()?->format('Y-m-d H:i') ?? '-',
            );
        }


        return new Response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="activities_report_last7days.txt"',
        ]);
    }

    #[Route('/reports/activities.pdf', name: 'app_reports_export_activities_pdf', methods: ['GET'])]
    public function exportActivitiesPdf(SupplyRequestRepository $supplyRequestRepository, OrderRepository $orderRepository): Response
    {
        $since = new \DateTimeImmutable('-7 days');

        $recentSupply = $supplyRequestRepository->createQueryBuilder('r')
            ->leftJoin('r.product', 'p')->addSelect('p')
            ->leftJoin('r.supplier', 's')->addSelect('s')
            ->andWhere('r.createdAt >= :since OR r.updatedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('r.updatedAt', 'DESC')
            ->getQuery()->getResult();

        $html = $this->renderView('reports/pdf/activities.html.twig', [
            'recentSupply' => $recentSupply,
            'generatedAt' => new \DateTimeImmutable(),
            'since' => $since,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="activities_report_last7days.pdf"',
        ]);
    }
}



