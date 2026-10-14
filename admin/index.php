<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$tabs = ['projects' => 'Projects', 'skills' => 'Skills', 'messages' => 'Messages', 'profile' => 'Profile'];
$tab  = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'projects';

/** Trimmed string from POST, or null if empty. */
function post_str(string $key): ?string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $v === '' ? null : $v;
}

/** URL from POST: empty → null, non-http(s) → validation error. */
function post_url(string $key, string $label, array &$errors): ?string
{
    $v = post_str($key);
    if ($v !== null && safe_url($v) === '') {
        $errors[] = "{$label} must be a link starting with http:// or https://";
    }
    return $v;
}

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $errors = [];

    switch ($action) {
        case 'save_project':
            $data = [
                'title'       => post_str('title'),
                'summary'     => post_str('summary'),
                'description' => post_str('description'),
                'tech'        => implode(', ', array_filter(array_map('trim', explode(',', (string) ($_POST['tech'] ?? ''))))),
                'image_url'   => post_url('image_url', 'Image URL', $errors),
                'demo_url'    => post_url('demo_url', 'Demo link', $errors),
                'repo_url'    => post_url('repo_url', 'Source code link', $errors),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
            ];
            if (!$data['title'] || !$data['summary']) {
                $errors[] = 'Title and summary are required.';
            }
            if ($errors) break;

            if ($id) {
                $sql = 'UPDATE projects SET title=:title, summary=:summary, description=:description, tech=:tech,
                        image_url=:image_url, demo_url=:demo_url, repo_url=:repo_url,
                        is_featured=:is_featured, sort_order=:sort_order WHERE id=:id';
                $data['id'] = $id;
            } else {
                $sql = 'INSERT INTO projects (title, summary, description, tech, image_url, demo_url, repo_url, is_featured, sort_order)
                        VALUES (:title, :summary, :description, :tech, :image_url, :demo_url, :repo_url, :is_featured, :sort_order)';
            }
            db()->prepare($sql)->execute($data);
            flash('success', $id ? 'Project updated.' : 'Project added.');
            redirect('index.php?tab=projects');

        case 'delete_project':
            db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
            flash('success', 'Project deleted.');
            redirect('index.php?tab=projects');

        case 'save_skill':
            $data = [
                'name'       => post_str('name'),
                'category'   => post_str('category') ?? 'Other',
                'level'      => max(0, min(100, (int) ($_POST['level'] ?? 50))),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            ];
            if (!$data['name']) {
                $errors[] = 'Skill name is required.';
                break;
            }
            if ($id) {
                $data['id'] = $id;
                db()->prepare('UPDATE skills SET name=:name, category=:category, level=:level, sort_order=:sort_order WHERE id=:id')->execute($data);
            } else {
                db()->prepare('INSERT INTO skills (name, category, level, sort_order) VALUES (:name, :category, :level, :sort_order)')->execute($data);
            }
            flash('success', 'Skill saved.');
            redirect('index.php?tab=skills');

        case 'delete_skill':
            db()->prepare('DELETE FROM skills WHERE id = ?')->execute([$id]);
            flash('success', 'Skill deleted.');
            redirect('index.php?tab=skills');

        case 'toggle_read':
            db()->prepare('UPDATE messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
            redirect('index.php?tab=messages');

        case 'delete_message':
            db()->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
            flash('success', 'Message deleted.');
            redirect('index.php?tab=messages');

        case 'save_profile':
            $data = [
                'full_name'    => post_str('full_name'),
                'headline'     => post_str('headline') ?? '',
                'about'        => post_str('about') ?? '',
                'location'     => post_str('location'),
                'email'        => post_str('email'),
                'github_url'   => post_url('github_url', 'GitHub', $errors),
                'telegram_url' => post_url('telegram_url', 'Telegram', $errors),
                'linkedin_url' => post_url('linkedin_url', 'LinkedIn', $errors),
                'avatar_url'   => post_url('avatar_url', 'Photo URL', $errors),
            ];
            if (!$data['full_name']) {
                $errors[] = 'Name is required.';
            }
            if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }
            if ($errors) break;

            db()->prepare(
                'INSERT INTO profile (id, full_name, headline, about, location, email, github_url, telegram_url, linkedin_url, avatar_url)
                 VALUES (1, :full_name, :headline, :about, :location, :email, :github_url, :telegram_url, :linkedin_url, :avatar_url)
                 ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), headline=VALUES(headline), about=VALUES(about),
                   location=VALUES(location), email=VALUES(email), github_url=VALUES(github_url),
                   telegram_url=VALUES(telegram_url), linkedin_url=VALUES(linkedin_url), avatar_url=VALUES(avatar_url)'
            )->execute($data);
            flash('success', 'Profile saved.');
            redirect('index.php?tab=profile');
    }

    // Only reached on validation errors — show them and stay on the form
    foreach ($errors as $err) {
        flash('error', $err);
    }
    $_SESSION['old_form'] = $_POST;
    $back = 'index.php?tab=' . urlencode($tab) . ($id ? '&edit=' . $id : '&edit=new');
    redirect($back);
}

// ---------- View data ----------
$flashes = take_flashes();
$oldForm = $_SESSION['old_form'] ?? null;
unset($_SESSION['old_form']);

$editId = $_GET['edit'] ?? null;
$unread = (int) db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();

/** Form field value: the failed submission first, then the database row. */
function field(array $row, string $key, ?array $old): string
{
    return e($old[$key] ?? $row[$key] ?? '');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — <?= e($tabs[$tab]) ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<header class="nav">
  <div class="container nav__inner">
    <a class="nav__logo" href="index.php">Admin<span>.</span></a>
    <div class="nav__actions">
      <a class="btn btn--ghost btn--sm" href="../index.php" target="_blank">View site ↗</a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn btn--ghost btn--sm">Sign out</button></form>
    </div>
  </div>
</header>

<main class="container admin">
  <nav class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="?tab=<?= $key ?>" class="<?= $key === $tab ? 'is-active' : '' ?>">
        <?= e($label) ?><?php if ($key === 'messages' && $unread): ?> <b class="count"><?= $unread ?></b><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($flashes as $f): ?>
    <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>

<?php /* ===================== PROJECTS ===================== */ if ($tab === 'projects'): ?>

  <?php if ($editId !== null):
      $row = [];
      if ($editId !== 'new') {
          $st = db()->prepare('SELECT * FROM projects WHERE id = ?');
          $st->execute([(int) $editId]);
          $row = $st->fetch() ?: [];
      }
  ?>
    <form class="card form" method="post">
      <h2><?= $row ? 'Edit project' : 'New project' ?></h2>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_project">
      <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
      <label>Title * <input name="title" required maxlength="150" value="<?= field($row, 'title', $oldForm) ?>"></label>
      <label>Summary * <input name="summary" required maxlength="300" value="<?= field($row, 'summary', $oldForm) ?>"></label>
      <label>Description <textarea name="description" rows="5"><?= field($row, 'description', $oldForm) ?></textarea></label>
      <label>Technologies (comma-separated) <input name="tech" maxlength="255" placeholder="PHP, MySQL, JavaScript" value="<?= field($row, 'tech', $oldForm) ?>"></label>
      <div class="form__row">
        <label>Demo link <input type="url" name="demo_url" value="<?= field($row, 'demo_url', $oldForm) ?>"></label>
        <label>Source code link <input type="url" name="repo_url" value="<?= field($row, 'repo_url', $oldForm) ?>"></label>
      </div>
      <label>Image URL <input type="url" name="image_url" value="<?= field($row, 'image_url', $oldForm) ?>"></label>
      <div class="form__row">
        <label>Sort order <input type="number" name="sort_order" value="<?= field($row, 'sort_order', $oldForm) ?: 0 ?>"></label>
        <label class="check"><input type="checkbox" name="is_featured" <?= ($oldForm['is_featured'] ?? $row['is_featured'] ?? 0) ? 'checked' : '' ?>> Featured project</label>
      </div>
      <div class="form__actions">
        <button class="btn btn--primary">Save</button>
        <a class="btn btn--ghost" href="?tab=projects">Cancel</a>
      </div>
    </form>
  <?php else:
      $projects = db()->query('SELECT * FROM projects ORDER BY is_featured DESC, sort_order, id DESC')->fetchAll();
  ?>
    <div class="toolbar">
      <h2>Projects (<?= count($projects) ?>)</h2>
      <a class="btn btn--primary btn--sm" href="?tab=projects&edit=new">+ Add project</a>
    </div>
    <div class="table-wrap card">
      <table>
        <thead><tr><th>Title</th><th>Technologies</th><th>★</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td><strong><?= e($p['title']) ?></strong><br><span class="muted"><?= e($p['summary']) ?></span></td>
            <td><?= e($p['tech']) ?></td>
            <td><?= $p['is_featured'] ? '★' : '' ?></td>
            <td><?= (int) $p['sort_order'] ?></td>
            <td class="actions">
              <a href="?tab=projects&edit=<?= (int) $p['id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this project?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_project"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="link-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?><tr><td colspan="5" class="muted">Nothing here yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php /* ===================== SKILLS ===================== */ elseif ($tab === 'skills'):
    $row = [];
    if ($editId !== null && $editId !== 'new') {
        $st = db()->prepare('SELECT * FROM skills WHERE id = ?');
        $st->execute([(int) $editId]);
        $row = $st->fetch() ?: [];
    }
    $skills = db()->query('SELECT * FROM skills ORDER BY category, sort_order, name')->fetchAll();
    $categories = array_unique(array_column($skills, 'category'));
?>
  <div class="admin-split">
    <form class="card form" method="post">
      <h2><?= $row ? 'Edit skill' : 'New skill' ?></h2>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_skill">
      <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
      <label>Name * <input name="name" required maxlength="80" value="<?= field($row, 'name', $oldForm) ?>"></label>
      <label>Category
        <input name="category" list="categories" maxlength="80" value="<?= field($row, 'category', $oldForm) ?>">
        <datalist id="categories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      </label>
      <label><span>Level: <output id="lvlOut"><?= field($row, 'level', $oldForm) ?: 50 ?></output>%</span>
        <input type="range" name="level" min="0" max="100" step="5" value="<?= field($row, 'level', $oldForm) ?: 50 ?>"
               oninput="lvlOut.value = this.value">
      </label>
      <label>Sort order <input type="number" name="sort_order" value="<?= field($row, 'sort_order', $oldForm) ?: 0 ?>"></label>
      <div class="form__actions">
        <button class="btn btn--primary">Save</button>
        <?php if ($row): ?><a class="btn btn--ghost" href="?tab=skills">Cancel</a><?php endif; ?>
      </div>
    </form>

    <div class="table-wrap card">
      <table>
        <thead><tr><th>Skill</th><th>Category</th><th>Level</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($skills as $s): ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['category']) ?></td>
            <td><div class="bar"><div class="bar__fill" style="width: <?= (int) $s['level'] ?>%"></div></div></td>
            <td class="actions">
              <a href="?tab=skills&edit=<?= (int) $s['id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this skill?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_skill"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="link-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php /* ===================== MESSAGES ===================== */ elseif ($tab === 'messages'):
    $messages = db()->query('SELECT * FROM messages ORDER BY is_read, created_at DESC')->fetchAll();
?>
  <div class="toolbar"><h2>Messages (<?= count($messages) ?>, unread: <?= $unread ?>)</h2></div>
  <div class="messages">
    <?php foreach ($messages as $m): ?>
      <article class="card message <?= $m['is_read'] ? '' : 'is-unread' ?>">
        <header>
          <div>
            <strong><?= e($m['name']) ?></strong>
            · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
          </div>
          <time class="muted"><?= e(date('M j, Y g:i A', strtotime($m['created_at']))) ?></time>
        </header>
        <p><?= nl2br(e($m['body'])) ?></p>
        <footer class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_read"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="link"><?= $m['is_read'] ? 'Mark as unread' : 'Mark as read' ?></button></form>
          <form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="link-danger">Delete</button></form>
        </footer>
      </article>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p class="muted">No messages yet.</p><?php endif; ?>
  </div>

<?php /* ===================== PROFILE ===================== */ else:
    $row = get_profile();
?>
  <form class="card form" method="post">
    <h2>Profile</h2>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_profile">
    <div class="form__row">
      <label>Full name * <input name="full_name" required maxlength="120" value="<?= field($row, 'full_name', $oldForm) ?>"></label>
      <label>Headline / job title <input name="headline" maxlength="200" value="<?= field($row, 'headline', $oldForm) ?>"></label>
    </div>
    <label>About me <textarea name="about" rows="6"><?= field($row, 'about', $oldForm) ?></textarea></label>
    <div class="form__row">
      <label>Location <input name="location" maxlength="120" value="<?= field($row, 'location', $oldForm) ?>"></label>
      <label>Email <input type="email" name="email" maxlength="190" value="<?= field($row, 'email', $oldForm) ?>"></label>
    </div>
    <div class="form__row">
      <label>GitHub <input type="url" name="github_url" value="<?= field($row, 'github_url', $oldForm) ?>"></label>
      <label>Telegram <input type="url" name="telegram_url" value="<?= field($row, 'telegram_url', $oldForm) ?>"></label>
    </div>
    <div class="form__row">
      <label>LinkedIn <input type="url" name="linkedin_url" value="<?= field($row, 'linkedin_url', $oldForm) ?>"></label>
      <label>Photo URL <input type="url" name="avatar_url" value="<?= field($row, 'avatar_url', $oldForm) ?>"></label>
    </div>
    <div class="form__actions"><button class="btn btn--primary">Save</button></div>
  </form>
<?php endif; ?>
</main>
</body>
</html>
