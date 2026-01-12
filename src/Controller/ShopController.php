<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {   
        $products = $productRepository->findActive();
        return $this->render('shop/index.html.twig', [
            'pageTitle' => 'Shop',
            'products' => $products,
        ]);
    }

    #[Route('/cart', name: 'app_cart_index', methods: ['GET'])]
    public function cart(SessionInterface $session, ProductRepository $productRepository): Response
    {
        $cart = $session->get('cart', []); // [productId => qty]

        $items = [];
        $total = 0;

        foreach ($cart as $productId => $qty) {
            $product = $productRepository->find($productId);
            if (!$product) continue;

            $lineTotal = (float) $product->getPrice() * (int) $qty;
            $total += $lineTotal;

            $items[] = [
                'product' => $product,
                'qty' => (int) $qty,
                'lineTotal' => $lineTotal,
            ];
        }

        return $this->render('cart/index.html.twig', [
            'pageTitle' => 'Cart',
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Product $product, Request $request, SessionInterface $session): Response
    {
        $qty = max(1, (int) $request->request->get('qty', 1));

        // ✅ Check stock BEFORE touching the cart
        if ($product->getQuantity() < $qty) {
            $this->addFlash('danger', 'Not enough stock for: '.$product->getName());
            return $this->redirectToRoute('app_shop_index');
        }

        $cart = $session->get('cart', []);
        $id = $product->getId();

        // Optional: prevent cart total qty exceeding stock
        $currentQty = (int) ($cart[$id] ?? 0);
        if ($product->getQuantity() < ($currentQty + $qty)) {
            $this->addFlash('danger', 'Not enough stock for: '.$product->getName());
            return $this->redirectToRoute('app_shop_index');
        }

        $cart[$id] = $currentQty + $qty;
        $session->set('cart', $cart);

        $this->addFlash('success', 'Added to cart.');
        return $this->redirectToRoute('app_shop_index');
    }


    #[Route('/cart/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        unset($cart[$product->getId()]);
        $session->set('cart', $cart);

        $this->addFlash('success', 'Removed from cart.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/cart/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(SessionInterface $session): Response
    {
        $session->remove('cart');
        $this->addFlash('success', 'Cart cleared.');
        return $this->redirectToRoute('app_cart_index');
    }
}
