<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Get the search term if provided
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Determine if delete mode is enabled (only applicable for admin users)
$delete_mode = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && isset($_GET['delete_mode'])) {
    $delete_mode = ($_GET['delete_mode'] == 1);
}

// Build the SQL query to fetch posts joined with user info
$sql = "
  SELECT DISTINCT
    p.post_id, p.title, p.content, p.image, p.created_at, u.username,
    (SELECT COUNT(*) FROM post_like WHERE post_id=p.post_id) AS like_count,
    (SELECT COUNT(*) FROM post_comment WHERE post_id=p.post_id) AS comment_count
  FROM post p
  JOIN user u ON p.user_id=u.user_id
  LEFT JOIN post_recipe pr ON p.post_id=pr.post_id
  LEFT JOIN recipe r ON pr.recipe_id=r.recipe_id
";
if ($search!=='') {
  $safe = mysqli_real_escape_string($conn,$search);
  $sql .= " WHERE p.title LIKE '%$safe%'
            OR p.content LIKE '%$safe%'
            OR r.recipe_title LIKE '%$safe%'";
}
$sql .= " ORDER BY p.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Community</title>
    <style>
        /* Basic page styling */
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        /* Container for posts feed */
        .container-wrapper {
            max-width: 800px;
            margin: 20px auto;
            padding: 10px;
        }
        .toolbar-wrapper {
            position: sticky;
            top: 10px; /* Adjust if your header is fixed */
            background-color: transparent; /* Transparent background */
            padding: 10px 0;
            backdrop-filter: blur(6px); /* Optional: blurred glass effect */
            -webkit-backdrop-filter: blur(6px); /* Safari support */
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        /* Toolbar styling */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .search-form {
            display: flex;
            flex-grow: 1;
            max-width: 500px;
        }
        .search-form input[type="text"] {
            flex: 1;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px 0 0 6px;
            font-size: 1em;
        }
        .search-form button {
            padding: 8px 14px;
            border: none;
            background-color: #3498db;
            color: white;
            font-size: 1em;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .search-form button:hover {
            background-color: #2980b9;
        }
        .create-post-btn,
        .delete-mode-btn {
            padding: 10px 18px;
            font-size: 1em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            white-space: nowrap;
            color: #fff;
        }
        .create-post-btn {
            background-color: #28a745;
        }
        .create-post-btn:hover {
            background-color: #218838;
        }
        .delete-mode-btn {
            background-color: #dc3545;
        }
        .delete-mode-btn:hover {
            background-color: #c82333;
        }
        /* Post card styling */
        .post-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .post-header {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
        }
        .post-username {
            font-weight: bold;
            color: #333;
        }
        .post-date {
            font-size: 0.9em;
            color: #999;
        }
        .post-image img {
            width: 100%;
            display: block;
        }
        .post-content {
            padding: 15px;
        }
        .post-title {
            margin: 0 0 10px;
            font-size: 1.4em;
            color: #333;
        }
        .post-caption {
            margin: 0 0 15px;
            color: #555;
            line-height: 1.6;
        }
        .post-tags {
            margin-top: 10px;
        }
        .post-tags .tag {
            display: inline-block;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 20px;
            margin-right: 6px;
            font-size: 0.85em;
        }
        .post-tags .tag:hover {
            background-color: #2c80b4;
        }
        .delete-post-btn {
            background-color: #dc3545;
            border: none;
            color: #fff;
            padding: 6px 12px;
            font-size: 0.9em;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .delete-post-btn:hover {
            background-color: #c82333;
        }
        .post-actions {
            padding: 10px 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 20px;
        }
        .post-actions button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        .post-actions button.disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 2000;
        }
        .modal-bg {
            position: absolute; top:0; left:0; right:0; bottom:0;
            background: rgba(0,0,0,0.4);
        }
        .modal-content {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            width: 90%; max-width: 400px;
            height: 60vh;
            display: flex;
            flex-direction: column;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .close-modal {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
            z-index: 10;
        }
        .close-modal:hover {
            color: #222;
        }
        .modal-comments {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            word-wrap: break-word;
            white-space: normal;
        }

        .modal-comments div {
            margin-bottom: 12px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            word-wrap: break-word;
            white-space: normal;
        }
        .modal-input {
            flex: 0 0 auto;
            display: flex;
            border-top: 1px solid #ddd;
        }
        .modal-input textarea {
            flex:1;
            border:none;
            padding:8px;
            resize:none;
        }
        .modal-input button {
            border:none;
            background:#28a745;
            color:#fff;
            padding:0 16px;
            cursor:pointer;
        }
    </style>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container-wrapper">
    <h1>Community Posts</h1>
    
    <!-- Toolbar with Search, Create Post, and Admin Delete Mode Toggle -->
    <div class="toolbar-wrapper">
        <div class="toolbar">
            <form class="search-form" method="get" action="community.php">
                <input type="text" name="search" placeholder="Search posts..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">🔍</button>
            </form>
            <button class="create-post-btn" onclick="window.location.href='create_post.php'">+ Create Post</button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <button class="delete-mode-btn" onclick="toggleDeleteMode()">
                    <?= $delete_mode ? "Disable Delete Mode" : "Enable Delete Mode" ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Post feed -->
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="post-card">
                <div class="post-header">
                    <span class="post-username"><?= htmlspecialchars($row['username']); ?></span>
                    <span class="post-date"><?= $row['created_at']; ?></span>
                </div>
                <?php if (!empty($row['image'])): ?>
                    <div class="post-image">
                        <img src="uploads/<?= htmlspecialchars($row['image']); ?>" alt="<?= htmlspecialchars($row['title']); ?>">
                    </div>
                <?php endif; ?>
                <div class="post-content">
                    <h2 class="post-title"><?= htmlspecialchars($row['title']); ?></h2>
                    <p class="post-caption"><?= nl2br(htmlspecialchars($row['content'])); ?></p>
                    <?php 
                        // Retrieve tags for this post
                        $post_id = $row['post_id'];
                        $tagQuery = "SELECT r.recipe_id, r.recipe_title 
                                     FROM post_recipe pr 
                                     JOIN recipe r ON pr.recipe_id = r.recipe_id 
                                     WHERE pr.post_id = $post_id";
                        $tagResult = mysqli_query($conn, $tagQuery);
                        if (mysqli_num_rows($tagResult) > 0) {
                            echo '<div class="post-tags">';
                            while ($tag = mysqli_fetch_assoc($tagResult)) {
                                echo '<a class="tag" href="recipe_page.php?recipe_id=' . $tag['recipe_id'] . '">' . htmlspecialchars($tag['recipe_title']) . '</a>';
                            }
                            echo '</div>';
                        }
                    ?>
                    <?php if ($delete_mode && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <button class="delete-post-btn"
                                onclick="confirmAndDelete(<?= $row['post_id'] ?>)">
                            Delete
                        </button>
                    <?php endif; ?>
                </div>
                <div class="post-actions">
                    <button class="like-btn <?= isset($_SESSION['user_id'])?'' : 'disabled' ?>"
                            data-post-id="<?= $row['post_id'] ?>">
                        ❤️ <span class="like-count"><?= $row['like_count'] ?></span>
                    </button>
                    <button class="comment-btn"
                            data-post-id="<?= $row['post_id'] ?>">
                        💬 <span class="comment-count"><?= $row['comment_count'] ?></span>
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; font-style: italic; color: #666;">No posts yet!</p>
    <?php endif; ?>
</div>

<div id="comment-modal" class="modal">
    <div class="modal-bg"></div>
        <div class="modal-content">
            <span class="close-modal">&times;</span> <!-- Move to top -->
            <div id="modal-comments" class="modal-comments"></div>
            <div class="modal-input">
                <textarea id="modal-comment-input" placeholder="Write a comment..."></textarea>
                <button id="modal-send-btn">Send</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let currentPostId = null;

  const modal       = document.getElementById('comment-modal');
  const modalBg     = modal.querySelector('.modal-bg');
  const closeBtn    = modal.querySelector('.close-modal');
  const commentsDiv = document.getElementById('modal-comments');
  const input       = document.getElementById('modal-comment-input');
  const sendBtn     = document.getElementById('modal-send-btn');

  sendBtn.type = 'button';  // avoid implicit form submit

  // 1) Open the modal & load comments
  document.querySelectorAll('.comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      currentPostId = btn.dataset.postId;
      console.log("Opening comments for post:", currentPostId);
      modal.style.display = 'block';
      loadComments(currentPostId);
    });
  });

  // 2) Close modal
  modalBg.addEventListener('click', () => modal.style.display = 'none');
  closeBtn.addEventListener('click', () => modal.style.display = 'none');

  // 3) Fetch & render comments
  function loadComments(postId) {
    commentsDiv.innerHTML = '<p>Loading…</p>';
    const cacheBuster = Date.now();
    fetch(`comment_post.php?post_id=${postId}&_=${cacheBuster}`)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(comments => {
        console.log("Fetched comments:", comments);
        commentsDiv.innerHTML = ''; 
        if (comments.length === 0) {
          commentsDiv.innerHTML = '<p>No comments yet.</p>';
          return;
        }
        comments.forEach(c => {
          const div = document.createElement('div');
          div.innerHTML = `
            <strong>${c.username}</strong>
            <small style="margin-left:8px;color:#777;">${c.created_at}</small>
            <p style="margin:4px 0;">${c.comment}</p>
          `;
          commentsDiv.appendChild(div);
        });
        // update badge count
        const badge = document.querySelector(`.comment-btn[data-post-id="${postId}"] .comment-count`);
        if (badge) badge.textContent = comments.length;
      })
      .catch(err => {
        console.error("Error loading comments:", err);
        commentsDiv.innerHTML = `<p style="color:red;">Failed to load comments</p>`;
      });
  }

  // 4) Send a new comment
  sendBtn.addEventListener('click', () => {
    const text = input.value.trim();
    if (!text || !currentPostId) {
      console.log("Nothing to send or no post selected.");
      return;
    }
    console.log("Sending comment:", text);
    fetch('comment_post.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `post_id=${currentPostId}&comment=${encodeURIComponent(text)}`
    })
    .then(res => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then(comments => {
      console.log("After POST, comments:", comments);
      input.value = '';
      loadComments(currentPostId);
    })
    .catch(err => {
      console.error("Error sending comment:", err);
      alert("Could not send comment. See console for details.");
    });
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendBtn.click();
    }
    if (e.key === 'Escape') {
        modal.style.display = 'none';
    }
  });
});

function toggleDeleteMode() {
    // Save scroll position
    sessionStorage.setItem('scrollPos', window.scrollY);

    // Toggle the URL
    const url = new URL(window.location.href);
    const current = url.searchParams.get("delete_mode");
    const newVal = (current === "1") ? "0" : "1";
    url.searchParams.set("delete_mode", newVal);

    // Reload the page with new delete_mode value
    window.location.href = url.toString();
}

// Restore scroll position on page load
window.addEventListener('load', () => {
    const scrollPos = sessionStorage.getItem('scrollPos');
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos));
        sessionStorage.removeItem('scrollPos');
    }
});

function confirmAndDelete(postId) {
    if (confirm('Are you sure you want to delete this post?')) {
        sessionStorage.setItem('scrollPos', window.scrollY);
        const url = new URL('delete_post.php', window.location.href);
        url.searchParams.set('post_id', postId);
        url.searchParams.set('delete_mode', '1'); // persist delete mode
        window.location.href = url.toString();
    }
}

// LIKE BUTTONS
document.querySelectorAll('.like-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (btn.classList.contains('disabled')) return;
    const postId = btn.dataset.postId;
    fetch('like_post.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'post_id='+postId
    })
    .then(r=>r.json())
    .then(data=>{
      btn.querySelector('.like-count').textContent = data.count;
      if (data.action==='liked') btn.classList.add('liked');
      else btn.classList.remove('liked');
    });
  });
});
</script>
</body>
</html>
