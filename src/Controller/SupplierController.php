<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplyRequestRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\SupplyRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/supplier')]
final class SupplierController extends AbstractController
{
    #[Route(name: 'app_supplier_index', methods: ['GET'])]
    public function index(Request $request, SupplierRepository $supplierRepository , SupplyRequestRepository $supplyRequestRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $dir = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $allowedSort = ['name', 'id'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'name';
        }

        $qb = $supplierRepository->createQueryBuilder('s')
            ->andWhere('s.isArchived = false');

        if ($q !== '') {
            $qb->andWhere('s.name LIKE :q')
            ->setParameter('q', '%'.$q.'%');
        }

        $qb->orderBy('s.'.$sort, $dir);

        $suppliers = $qb->getQuery()->getResult();
        $hasNewRequests = $supplyRequestRepository->count(['status' => 'Pending']) > 0;
        return $this->render('supplier/index.html.twig', [
            'suppliers' => $suppliers,
            'pageTitle' => 'Suppliers',
            'q' => $q,
            'sort' => $sort,
            'dir' => strtolower($dir),
            'hasNewRequests' => $hasNewRequests,
        ]);
    }

    #[Route('/new', name: 'app_supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $supplier = new Supplier();
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($supplier);
            $entityManager->flush();

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/new.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_supplier_show', methods: ['GET'])]
    public function show(Supplier $supplier): Response
    {
        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/edit.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/supplier/{id}', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$supplier->getId(), (string) $request->request->get('_token'))) {
            $supplier->setIsArchived(true);
            $em->flush();

            $this->addFlash('success', 'Supplier archived.');
        }

        return $this->redirectToRoute('app_supplier_index');
    }

    #[Route('/supplier/requests', name: 'app_supplier_requests', methods: ['GET'])]
    public function requests(\App\Repository\SupplyRequestRepository $supplyRequestRepository): Response
    {
        $pendingRequests = $supplyRequestRepository->findBy(['status' => 'Pending'], ['createdAt' => 'DESC']);

        $recentProcessed = $supplyRequestRepository->createQueryBuilder('r')
        ->andWhere('r.status IN (:statuses)')
        ->setParameter('statuses', ['Confirmed', 'Rejected'])
        ->orderBy('r.updatedAt', 'DESC')
        ->setMaxResults(8)
        ->getQuery()
        ->getResult();


        return $this->render('supplier/requests.html.twig', [
            'pageTitle' => 'Suppliers',
            'pendingRequests' => $pendingRequests,
            'recentProcessed' => $recentProcessed,
        ]);
    }

    #[Route('/supplier/requests/{id}/confirm', name: 'app_supply_request_confirm', methods: ['POST'])]
    public function confirm(
        SupplyRequest $supplyRequest,
        EntityManagerInterface $em
    ): Response {
        if ($supplyRequest->getStatus() !== 'Pending') {
            $this->addFlash('warning', 'This request is already processed.');
            return $this->redirectToRoute('app_supplier_requests');
        }

        $supplyRequest->setStatus('Confirmed');
        $supplyRequest->setUpdatedAt(new \DateTimeImmutable());

        // Increase stock on confirm
        $product = $supplyRequest->getProduct();
        $product->setQuantity($product->getQuantity() + $supplyRequest->getRequestedQty());
        $product->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Request confirmed and stock updated.');
        return $this->redirectToRoute('app_supplier_requests');
    }

    #[Route('/supplier/requests/{id}/reject', name: 'app_supply_request_reject', methods: ['POST'])]
    public function reject(
        SupplyRequest $supplyRequest,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($supplyRequest->getStatus() !== 'Pending') {
            $this->addFlash('warning', 'This request is already processed.');
            return $this->redirectToRoute('app_supplier_requests');
        }

        $supplyRequest->setStatus('Rejected');
        $supplyRequest->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Request rejected.');
        return $this->redirectToRoute('app_supplier_requests');
    }
}
