<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SupplyRequestRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'createdAt');
        $dir = strtolower((string) $request->query->get('dir', 'asc')) === 'asc' ? 'ASC' : 'DESC';

        $allowedSort = ['name', 'price', 'createdAt', 'updatedAt'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'createdAt';
        }

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.supplier', 's')
            ->addSelect('s')
            ->andWhere('p.isArchived = false');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.category LIKE :q OR p.serialNumber LIKE :q')
            ->setParameter('q', '%'.$q.'%');
        }

        $qb->orderBy('p.'.$sort, $dir);

        $products = $qb->getQuery()->getResult();
        $priceLabels = [];
        $priceValues = [];

        foreach ($products as $p) {
            $priceLabels[] = $p->getName();
            $priceValues[] = (float) $p->getPrice();
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'pageTitle' => 'Products',
            'q' => $q,
            'sort' => $sort,
            'dir' => strtolower($dir),
            'priceChart' => [
                'labels' => $priceLabels,
                'values' => $priceValues,
            ],
        ]);
    }



    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move($this->getParameter('product_upload_dir'), $newFilename);

                $product->setImageName($newFilename);
            }
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move($this->getParameter('product_upload_dir'), $newFilename);

                $product->setImageName($newFilename);
                $product->setUpdatedAt(new \DateTimeImmutable());
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        \App\Repository\SupplyRequestRepository $supplyRequestRepository
    ): Response {
        if (!$this->isCsrfTokenValid('delete'.$product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_product_index');
        }

        // ✅ HARD BLOCK: if any supply_request references this product, refuse deletion
        //if ($supplyRequestRepository->count(['product' => $product]) > 0) {
        //    $this->addFlash('danger', 'Cannot delete this product because it has supply requests.');
        //    return $this->redirectToRoute('app_product_index');
        //}

        

        $product->setIsArchived(true);
        $entityManager->flush();

        $this->addFlash('success', 'Product archived.');
        return $this->redirectToRoute('app_product_index');

    }

}
