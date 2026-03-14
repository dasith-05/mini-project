<?php
session_start();

require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/config.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    log_error('Database connection failed', ['error' => $e->getMessage()]);
    header('HTTP/1.1 503 Service Unavailable');
    exit('Service temporarily unavailable.');
}

$profile_id = null;
if (!empty($_GET['id'])) {
    $profile_id = (int) $_GET['id'];
} elseif (!empty($_GET['user'])) {
    $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
    $stmt->execute([trim($_GET['user'])]);
    $profile_id = (int) $stmt->fetchColumn();
}

if (!$profile_id) {
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT id, name, student_id, contact FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $user['id'];

// Items this user reported (active only for public; own profile can show all)
$stmt = $db->prepare("SELECT id, title, description, item_type, floor, status FROM items WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$profile_id]);
$reported_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reported_active = array_filter($reported_items, function ($i) { return $i['status'] === 'active'; });
$reported_resolved = array_filter($reported_items, function ($i) { return $i['status'] === 'resolved'; });

// Items this user resolved (returned to owners)
$stmt = $db->prepare("
    SELECT i.id, i.title, i.item_type, i.floor, u.name AS owner_name
    FROM items i
    JOIN users u ON i.user_id = u.id
    WHERE i.resolved_by_user_id = ? AND i.status = 'resolved'
    ORDER BY i.id DESC
");
$stmt->execute([$profile_id]);
$resolved_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resolve_count = count($resolved_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> • TraceIt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .logo-font { font-family: 'Space Grotesk', sans-serif; }
        body { background-color: #0c4a6e; background-image: linear-gradient(rgba(14, 165, 233, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(14, 165, 233, 0.08) 1px, transparent 1px); background-size: 40px 40px; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="min-h-screen text-white">
    <div class="max-w-4xl mx-auto px-6 py-10">
        <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="<?php echo $is_own_profile ? 'index.php' : 'top-tracers.php'; ?>" class="text-zinc-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-left text-xl"></i>
                </a>
                <h1 class="logo-font text-3xl font-bold tracking-tighter">Profile</h1>
            </div>
            <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="flex items-center gap-3">
                <?php if (!$is_own_profile): ?>
                    <a href="profile.php?id=<?php echo (int) $_SESSION['user_id']; ?>" class="text-sky-400 hover:text-sky-300 text-sm font-medium">My profile</a>
                <?php endif; ?>
                <a href="index.php" class="text-zinc-400 hover:text-white text-sm">Map</a>
                <a href="top-tracers.php" class="text-amber-400 hover:text-amber-300 text-sm">Top Tracers</a>
                <a href="index.php?logout=1" class="bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white px-4 py-2 rounded-xl text-sm font-bold">Logout</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="glass-panel rounded-2xl border border-white/10 p-8 mb-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                <div class="w-20 h-20 rounded-2xl bg-sky-500/20 border border-sky-500/30 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-user text-4xl text-sky-400"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-zinc-400 mt-0.5"><?php echo htmlspecialchars($user['student_id']); ?></p>
                    <?php if ($is_own_profile): ?>
                        <p class="text-zinc-500 text-sm mt-1"><i class="fa-solid fa-phone mr-1"></i> <?php echo htmlspecialchars($user['contact']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($resolve_count > 0): ?>
                <div class="ml-auto shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <i class="fa-solid fa-trophy text-amber-400"></i>
                    <span class="font-bold text-amber-400"><?php echo $resolve_count; ?></span>
                    <span class="text-zinc-400 text-sm">returned</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="glass-panel rounded-2xl border border-white/10 p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-map-pin text-sky-400"></i>
                    Reported items (<?php echo count($reported_active); ?> active)
                </h3>
                <?php if (empty($reported_items)): ?>
                    <p class="text-zinc-500 text-sm">No reports yet.</p>
                <?php else: ?>
                    <ul class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($reported_items as $it): ?>
                            <li class="p-3 rounded-xl bg-zinc-800/50 border border-zinc-700/50">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-white truncate"><?php echo htmlspecialchars($it['title']); ?></span>
                                    <span class="shrink-0 text-xs px-2 py-0.5 rounded <?php echo $it['item_type'] === 'lost' ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-400'; ?>">
                                        <?php echo $it['item_type']; ?>
                                    </span>
                                </div>
                                <?php if (!empty($it['description'])): ?>
                                    <p class="text-zinc-500 text-xs mt-1 line-clamp-2"><?php echo htmlspecialchars($it['description']); ?></p>
                                <?php endif; ?>
                                <p class="text-zinc-600 text-xs mt-1"><?php echo htmlspecialchars($it['floor']); ?> • <?php echo $it['status'] === 'active' ? 'Active' : 'Resolved'; ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="glass-panel rounded-2xl border border-white/10 p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-hand-holding-heart text-emerald-400"></i>
                    Returned to owners (<?php echo $resolve_count; ?>)
                </h3>
                <?php if (empty($resolved_items)): ?>
                    <p class="text-zinc-500 text-sm">Has not returned any items yet.</p>
                <?php else: ?>
                    <ul class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($resolved_items as $it): ?>
                            <li class="p-3 rounded-xl bg-zinc-800/50 border border-zinc-700/50">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-white truncate"><?php echo htmlspecialchars($it['title']); ?></span>
                                    <span class="shrink-0 text-xs text-emerald-400">returned</span>
                                </div>
                                <p class="text-zinc-500 text-xs mt-1">to <?php echo htmlspecialchars($it['owner_name']); ?> • <?php echo htmlspecialchars($it['floor']); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
