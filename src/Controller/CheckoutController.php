<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function index(SessionInterface $session, ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $cart = $session->get('cart', []); 

        $items = [];
        $total = 0.0;

        foreach ($cart as $productId => $qty) {
            $product = $productRepository->find($productId);
            if (!$product) {
                continue;
            }

            $qty = (int) $qty;
            $lineTotal = (float) $product->getPrice() * $qty;
            $total += $lineTotal;

            $items[] = [
                'product' => $product,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
            ];
        }

        return $this->render('checkout/index.html.twig', [
            'pageTitle' => 'Checkout',
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/checkout/place', name: 'app_checkout_place', methods: ['POST'])]
    public function place(
        Request $request,
        SessionInterface $session,
        ProductRepository $productRepository,
        EntityManagerInterface $em
    ): Response {
        // CSRF
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_checkout');
        }

        $cart = $session->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('warning', 'Your cart is empty.');
            return $this->redirectToRoute('app_shop_index');
        }

        $order = new Order();

        // If your Order has setUser(), attach logged user (only if exists)
        $order->setUser($this->getUser());
        $total = 0.0;

        $em->beginTransaction();
        try {
            foreach ($cart as $productId => $qty) {
                $product = $productRepository->find($productId);
                if (!$product) {
                    continue;
                }

                $qty = (int) $qty;

                // Stock check
                if ($product->getQuantity() < $qty) {
                    $em->rollback();
                    $this->addFlash('danger', 'Not enough stock for: '.$product->getName());
                    return $this->redirectToRoute('app_cart_index');
                }

                $item = new OrderItem();

                // IMPORTANT: your relation name is likely orderRef (because "order" reserved)
                // so setters are usually setOrderRef() / getOrderRef().
                if (method_exists($item, 'setOrderRef')) {
                    $item->setOrderRef($order);
                } else {
                    // fallback if you named it "order"
                    $item->setOrder($order);
                }

                $item->setProduct($product);
                $item->setQuantity($qty);
                $item->setUnitPrice($product->getPrice());

                $lineTotal = (float) $product->getPrice() * $qty;
                $total += $lineTotal;

                // Reduce stock
                $product->setQuantity($product->getQuantity() - $qty);

                $em->persist($item);
            }

            $order->setTotalAmount((string) number_format($total, 2, '.', ''));

            // You can mark as Paid if you want; for now keep Pending or set Confirmed
            if (method_exists($order, 'setStatus')) {
                $order->setStatus('Paid');
            }

            $em->persist($order);
            $em->flush();
            $em->commit();

            // Clear cart
            $session->remove('cart');

            return $this->redirectToRoute('app_checkout_success', ['id' => $order->getId()]);
        } catch (\Throwable $e) {
            $em->rollback();
            throw $e;
        }
    }

    #[Route('/checkout/success/{id}', name: 'app_checkout_success', methods: ['GET'])]
    public function success(Order $order): Response
    {
        return $this->render('checkout/success.html.twig', [
            'pageTitle' => 'Success',
            'order' => $order,
        ]);
    }
}
