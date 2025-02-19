<?php
// budgets.php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle budget operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['set_budget'])) {
        $category_id = $_POST['category_id'];
        $amount = $_POST['amount'];
        $month = $_POST['month'];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO budgets (user_id, category_id, amount, month) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE amount = ?
            ");
            $stmt->execute([$user_id, $category_id, $amount, $month, $amount]);
            $message = "Budget successfully updated!";
        } catch(PDOException $e) {
            $error = "Error updating budget";
        }
    }
}

// Get all categories
$categories = getCategories($pdo, $user_id);

// Get current month's budgets
$current_month = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name,
           (SELECT COALESCE(SUM(amount), 0) 
            FROM expenses 
            WHERE category_id = b.category_id 
            AND user_id = b.user_id 
            AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(b.month, '%Y-%m')
           ) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND DATE_FORMAT(b.month, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
");
$stmt->execute([$user_id, $current_month]);
$budgets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - Budget Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Budget Planner</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="budgets.php">Budgets</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Set Budget Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Set Monthly Budget</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="budgetForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="amount" class="form-control" required min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Month</label>
                            <input type="month" name="month" class="form-control" required 
                                   value="<?= date('Y-m') ?>">
                        </div>
                    </div>
                    <button type="submit" name="set_budget" class="btn btn-primary mt-3">Set Budget</button>
                </form>
            </div>
        </div>

        <!-- Budget Overview -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Budget Overview</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Budget</th>
                                <th>Spent</th>
                                <th>Remaining</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                                <?php 
                                    $remaining = $budget['amount'] - $budget['spent'];
                                    $percentage = ($budget['spent'] / $budget['amount']) * 100;
                                    $progressClass = $percentage >= 90 ? 'bg-danger' : 
                                                   ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($budget['category_name']) ?></td>
                                    <td>$<?= number_format($budget['amount'], 2) ?></td>
                                    <td>$<?= number_format($budget['spent'], 2) ?></td>
                                    <td class="<?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                                        $<?= number_format($remaining, 2) ?>
                                    </td>
                                    <td style="width: 200px;">
                                        <div class="progress">
                                            <div class="progress-bar <?= $progressClass ?>"
                                                 role="progressbar"
                                                 style="width: <?= min($percentage, 100) ?>%"
                                                 aria-valuenow="<?= $percentage ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                                onclick="editBudget('<?= $budget['category_name'] ?>', 
                                                                  <?= $budget['amount'] ?>, 
                                                                  <?= $budget['category_id'] ?>)">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Budget Modal -->
    <div class="modal fade" id="editBudgetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editBudgetForm">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" id="edit_category_name" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="amount" id="edit_amount" 
                                       class="form-control" required min="0" step="0.01">
                            </div>
                        </div>
                        <button type="submit" name="set_budget" class="btn btn-primary">Update Budget</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit budget modal functionality
        function editBudget(categoryName, amount, categoryId) {
            document.getElementById('edit_category_name').value = categoryName;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_category_id').value = categoryId;
            
            const modal = new bootstrap.Modal(document.getElementById('editBudgetModal'));
            modal.show();
        }

        // Form validation
        document.getElementById('budgetForm').addEventListener('submit', function(e) {
            const amount = this.querySelector('[name="amount"]').value;
            if (amount <= 0) {
                e.preventDefault();
                alert('Amount must be greater than 0');
            }
        });

        // Currency formatting
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = parseFloat(this.value).toFixed(2);
                }
            });
        });
    </script>
</body>
</html>