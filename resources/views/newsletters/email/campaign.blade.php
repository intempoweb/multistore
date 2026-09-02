@include('newsletters.email.layout', [
    'newsletter' => $newsletter,
    'store' => $store,
    'products' => $products,
])
