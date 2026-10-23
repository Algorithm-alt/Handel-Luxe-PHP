<?php
$sub = str_replace('products/', '', $_GET['route'] ?? '');

switch ($sub) {
    case 'categories':
        handleCategories();
        break;
    case 'featured/all':
        handleFeatured();
        break;
    case 'new/arrivals':
        handleNewArrivals();
        break;
    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') handleList();
        break;
    default:
        if (preg_match('#^(\d+)$#', $sub, $m)) {
            handleSingle($m[1]);
        } else {
            jsonResponse(['error' => 'Not found'], 404);
        }
}

function handleList() {
    $db = getDB();
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 24;
    $offset = ($page - 1) * $limit;

    // Out-of-stock products stay listed — the frontend shows an
    // "Out of stock" badge and disables Add to Cart
    $where = "WHERE 1=1";
    $types = '';
    $params = [];

    if ($category) {
        $where .= " AND p.category_id = ?";
        $types .= 'i';
        $params[] = (int)$category;
    }
    if ($search) {
        $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $types .= 'ss';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM handel_products p $where");
    if ($types) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = ceil($total / $limit);

    $orderBy = "ORDER BY p.created_at DESC";
    if ($sort === 'price_asc') $orderBy = "ORDER BY p.price ASC";
    elseif ($sort === 'price_desc') $orderBy = "ORDER BY p.price DESC";

    $query = "SELECT p.*, c.name as category_name FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id $where $orderBy LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result();
    $stmt->close();

    $products = [];
    while ($row = $rows->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $products[] = $row;
    }

    jsonResponse(['products' => $products, 'page' => $page, 'totalPages' => (int)$totalPages, 'total' => (int)$total]);
}

function handleCategories() {
    $db = getDB();
    // product_count and image are extras for the homepage category showcase
    // (additive fields; the products page still reads only id/name)
    $result = $db->query("SELECT c.*,
        (SELECT COUNT(*) FROM handel_products WHERE category_id = c.id) as product_count,
        (SELECT image FROM handel_products WHERE category_id = c.id ORDER BY created_at DESC LIMIT 1) as image
        FROM handel_categories c ORDER BY c.id");
    $cats = [];
    while ($row = $result->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $cats[] = $row;
    }
    jsonResponse($cats);
}

function handleFeatured() {
    $db = getDB();
    $result = $db->query("SELECT p.*, c.name as category_name FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id WHERE p.featured = 1 ORDER BY p.created_at DESC LIMIT 8");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $products[] = $row;
    }
    jsonResponse($products);
}

function handleNewArrivals() {
    $db = getDB();
    $result = $db->query("SELECT p.*, c.name as category_name FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 4");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $products[] = $row;
    }
    jsonResponse($products);
}

function handleSingle($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Product not found'], 404);
    $product = $result->fetch_assoc();
    $product['image'] = image_url($product['image']);
    // Products created before the gallery existed get seeded from their
    // single image column so the detail page always has a gallery.
    ensureProductGallery((int)$product['id']);
    $product['images'] = getProductImages((int)$product['id']);
    jsonResponse($product);
}
