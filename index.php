<?php
require __DIR__ . '/includes/bootstrap.php';

// ---------- Contact form handling (POST → Redirect → GET) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $body    = trim((string) ($_POST['message'] ?? ''));
    $honeypot = (string) ($_POST['website'] ?? ''); // hidden trap field for bots

    $errors = [];
    if ($honeypot !== '') {
        $errors[] = 'Message rejected.';
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors[] = 'Please enter your name (2 to 120 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 5000) {
        $errors[] = 'Your message must be between 10 and 5000 characters.';
    }
    if (time() - ($_SESSION['last_message_at'] ?? 0) < 60) {
        $errors[] = 'Too many messages. Please try again in a minute.';
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        $_SESSION['old'] = compact('name', 'email', 'body');
    } else {
        $stmt = db()->prepare('INSERT INTO messages (name, email, body, ip) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $body, $_SERVER['REMOTE_ADDR'] ?? null]);
        $_SESSION['last_message_at'] = time();
        flash('success', 'Thank you! Your message has been sent — I\'ll get back to you soon.');
    }
    redirect('index.php#contact');
}

// ---------- Page data ----------
$profile  = get_profile();
$projects = db()->query('SELECT * FROM projects ORDER BY is_featured DESC, sort_order, id DESC')->fetchAll();
$skillRows = db()->query('SELECT * FROM skills ORDER BY category, sort_order, name')->fetchAll();

$skills = [];
foreach ($skillRows as $s) {
    $skills[$s['category']][] = $s;
}

$flashes = take_flashes();
$old = $_SESSION['old'] ?? ['name' => '', 'email' => '', 'body' => ''];
unset($_SESSION['old']);

$socials = array_filter([
    'GitHub'   => safe_url($profile['github_url']),
    'Telegram' => safe_url($profile['telegram_url']),
    'LinkedIn' => safe_url($profile['linkedin_url']),
]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($profile['full_name']) ?> — Portfolio</title>
  <meta name="description" content="<?= e($profile['headline']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <script>
    // Apply the saved theme before first paint to avoid a flash
    document.documentElement.classList.add('js');
    try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
  </script>
</head>
<body>

<header class="nav">
  <div class="container nav__inner">
    <a href="#top" class="nav__logo"><?= e(initials($profile['full_name'])) ?><span>.</span></a>
    <nav class="nav__links" id="navLinks">
      <a href="#about">About</a>
      <a href="#skills">Skills</a>
      <a href="#projects">Projects</a>
      <a href="#contact">Contact</a>
    </nav>
    <div class="nav__actions">
      <button class="icon-btn" id="themeToggle" aria-label="Toggle theme">◐</button>
      <button class="icon-btn nav__burger" id="burger" aria-label="Menu">☰</button>
    </div>
  </div>
</header>

<main id="top">

  <!-- Hero -->
  <section class="hero">
    <div class="container hero__inner">
      <div class="hero__text reveal">
        <p class="eyebrow">Hi, my name is</p>
        <h1><?= e($profile['full_name']) ?></h1>
        <p class="hero__headline"><?= e($profile['headline']) ?></p>
        <?php if ($profile['location']): ?>
          <p class="muted">📍 <?= e($profile['location']) ?></p>
        <?php endif; ?>
        <div class="hero__cta">
          <a class="btn btn--primary" href="#projects">View projects</a>
          <a class="btn btn--ghost" href="#contact">Get in touch</a>
        </div>
      </div>
      <div class="hero__avatar reveal">
        <?php if (safe_url($profile['avatar_url'])): ?>
          <img src="<?= e($profile['avatar_url']) ?>" alt="<?= e($profile['full_name']) ?>">
        <?php else: ?>
          <div class="avatar-placeholder"><?= e(initials($profile['full_name'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- About -->
  <section class="section" id="about">
    <div class="container narrow reveal">
      <h2 class="section__title">About me</h2>
      <p class="lead"><?= nl2br(e($profile['about'])) ?></p>
      <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as $label => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($label) ?> ↗</a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Skills -->
  <section class="section section--alt" id="skills">
    <div class="container">
      <h2 class="section__title reveal">Skills</h2>
      <div class="skills-grid">
        <?php foreach ($skills as $category => $items): ?>
          <div class="card reveal">
            <h3><?= e($category) ?></h3>
            <ul class="skill-list">
              <?php foreach ($items as $s): ?>
                <li>
                  <div class="skill-list__row">
                    <span><?= e($s['name']) ?></span>
                    <span class="muted"><?= (int) $s['level'] ?>%</span>
                  </div>
                  <div class="bar"><div class="bar__fill" style="--w: <?= (int) $s['level'] ?>%"></div></div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <?php if (!$skills): ?>
          <p class="muted">No skills added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Projects -->
  <section class="section" id="projects">
    <div class="container">
      <h2 class="section__title reveal">Projects</h2>

      <?php
      $allTech = [];
      foreach ($projects as $p) {
          foreach (array_filter(array_map('trim', explode(',', $p['tech']))) as $t) {
              $allTech[$t] = true;
          }
      }
      ksort($allTech);
      ?>
      <?php if ($allTech): ?>
        <div class="filters reveal" id="filters">
          <button class="chip is-active" data-filter="*">All</button>
          <?php foreach (array_keys($allTech) as $t): ?>
            <button class="chip" data-filter="<?= e($t) ?>"><?= e($t) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="projects-grid">
        <?php foreach ($projects as $p):
            $tech = array_filter(array_map('trim', explode(',', $p['tech'])));
            $demo = safe_url($p['demo_url']);
            $repo = safe_url($p['repo_url']);
            $img  = safe_url($p['image_url']);
        ?>
          <article class="project card reveal" data-tech="<?= e(implode('|', $tech)) ?>">
            <div class="project__cover">
              <?php if ($img): ?>
                <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
              <?php else: ?>
                <span><?= e(mb_substr($p['title'], 0, 1)) ?></span>
              <?php endif; ?>
              <?php if ($p['is_featured']): ?><em class="badge">★ Featured</em><?php endif; ?>
            </div>
            <div class="project__body">
              <h3><?= e($p['title']) ?></h3>
              <p><?= e($p['summary']) ?></p>
              <?php if ($p['description']): ?>
                <details>
                  <summary>Read more</summary>
                  <p class="muted"><?= nl2br(e($p['description'])) ?></p>
                </details>
              <?php endif; ?>
              <div class="tags">
                <?php foreach ($tech as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?>
              </div>
              <div class="project__links">
                <?php if ($demo): ?><a href="<?= e($demo) ?>" target="_blank" rel="noopener">Live demo ↗</a><?php endif; ?>
                <?php if ($repo): ?><a href="<?= e($repo) ?>" target="_blank" rel="noopener">Source ↗</a><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (!$projects): ?>
        <p class="muted">No projects added yet.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- Contact -->
  <section class="section section--alt" id="contact">
    <div class="container narrow">
      <h2 class="section__title reveal">Get in touch</h2>
      <p class="muted reveal">Have a project in mind or a question? Send me a message — I usually reply within a day.
        <?php if ($profile['email']): ?>Or email me at <a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a>.<?php endif; ?>
      </p>

      <?php foreach ($flashes as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>

      <form class="form card reveal" method="post" action="index.php" novalidate>
        <?= csrf_field() ?>
        <div class="form__row">
          <label>Name
            <input type="text" name="name" required minlength="2" maxlength="120" value="<?= e($old['name']) ?>">
          </label>
          <label>Email
            <input type="email" name="email" required maxlength="190" value="<?= e($old['email']) ?>">
          </label>
        </div>
        <label>Message
          <textarea name="message" rows="6" required minlength="10" maxlength="5000"><?= e($old['body']) ?></textarea>
        </label>
        <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <button class="btn btn--primary" type="submit">Send message</button>
      </form>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container">
    © <?= date('Y') ?> <?= e($profile['full_name']) ?> · Built with PHP &amp; MySQL
  </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
