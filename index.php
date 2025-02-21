<?php
// index.php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$categories = getCategories($pdo, $user_id);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        // Validate that all required fields are present
        if (isset($_POST['category'], $_POST['amount'], $_POST['date'])) {
            $description = isset($_POST['description']) ? $_POST['description'] : '';
            
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $_POST['category'],
                $_POST['amount'],
                $_POST['description'],
                $_POST['date']
            ]);
            $message = "Expense added successfully!";
        } else {
            $error = "Please fill in all required fields";
        }
    }
    
    if (isset($_POST['set_budget'])) {
        
        // Validate that all required fields are present
        if (isset($_POST['category_id'], $_POST['amount'], $_POST['date'])) {
            $category_id = $_POST['category_id'];
            $amount = $_POST['amount'];
            $date = $_POST['date'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO budgets (user_id, category_id, amount, date) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE amount = ?
                ");
                $stmt->execute([$user_id, $category_id, $amount, $date, $amount]);
                $message = "Budget successfully updated!";
            } catch(PDOException $e) {
                $error = "Error updating budget: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all required fields for budget setting";
        }
    }
}
// Get expenses and budgets and categories
$categories = getCategories($pdo, $user_id);
$current_date = date('Y-m-01');
$stmt = $pdo->prepare("

   SELECT b.*, c.name as category_name,
           (SELECT COALESCE(SUM(amount), 0) 
            FROM expenses 
            WHERE category_id = b.category_id 
            AND user_id = b.user_id 
            AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(b.date, '%Y-%m')
           ) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND DATE_FORMAT(b.date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
");
$stmt->execute([$user_id, $current_date]);
$expenses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name 
    FROM budgets b 
    JOIN categories c ON b.category_id = c.id 
    WHERE b.user_id = ? AND DATE_FORMAT(b.date, '%Y-%m') = ?
");
$stmt->execute([$user_id, $current_date]);
$budgets = $stmt->fetchAll();
?>

<!DOCTYPE html>    
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Budget Planner</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
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
        <div class="class mt -8">
        <!-- Add Expense Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Expense</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="expenseForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="amount" class="form-control" placeholder="Amount" required step="0.01">
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="description" class="form-control" placeholder="Description">
                        </div>
                    </div>
                    <button type="submit" name="add_expense" class="btn btn-primary mt-3">Add Expense</button>
                </form>
            </div>
        </div>
        <!--set budget form-->
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
                            <input type="date" name="date" class="form-control" required 
                                   value="<?= date('Y-m') ?>">
                        </div>
                    </div>
                    <button type="submit" name="set_budget" class="btn btn-primary mt-3">Set Budget</button>
                </form>
            </div>
        </div>
        </div>

        <div class="row">
            <!-- Expenses List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Expenses</h5>
                        <div>
                            <input type="date" id="monthFilter" class="form-control" value="<?= date('Y-m') ?>">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="expensesList">
                                    <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($expense['date'])) ?></td>
                                        <td><?= htmlspecialchars($expense['category_name']) ?></td>
                                        <td>UGX <?= number_format($expense['amount'], 0) ?></td>
                                        <td><?= htmlspecialchars($expense['description'] ?? '') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="deleteExpense(<?= $expense['id'] ?>)">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                <?php require_once'budgets.php';?>
            
           
        </div>
            </div>

            <!-- Budget Overview for monthly -->
            <div class="col-md-4">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Monthly Summary</h5>
        </div>
        <div class="card-body">
            <?php
            // Calculate monthly totals
            $total_budget = 0;
            $total_expenses = 0;
            foreach ($budgets as $budget) {
                $total_budget += $budget['amount'];
                foreach ($expenses as $expense) {
                    if ($expense['category_id'] == $budget['category_id']) {
                        $total_expenses += $expense['amount'];
                    }
                }
            }
            $remaining = $total_budget - $total_expenses;
            $percentage_used = $total_budget > 0 ? ($total_expenses / $total_budget) * 100 : 0;
            ?>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <h6>Total Monthly Budget:</h6>
                    <span class="fw-bold">UGX <?= number_format($total_budget, 0) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <h6>Total Expenses:</h6>
                    <span class="fw-bold">UGX <?= number_format($total_expenses, 0) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <h6>Remaining Budget:</h6>
                    <span class="fw-bold <?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                        UGX <?= number_format($remaining, 0) ?>
                    </span>
                </div>
                <div class="progress mt-2" style="height: 20px;">
                    <div class="progress-bar <?= $percentage_used > 100 ? 'bg-danger' : ($percentage_used > 80 ? 'bg-warning' : 'bg-success') ?>" 
                         role="progressbar" 
                         style="width: <?= min($percentage_used, 100) ?>%"
                         aria-valuenow="<?= $percentage_used ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?= number_format($percentage_used, 0) ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>

    
   

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Budget vs Spending</h5>
        </div>
        <div class="card-body">
            <?php
            // Calculate over-budget categories
            $overBudgetCategories = [];
            foreach ($budgets as $budget) {
                $categorySpent = 0;
                foreach ($expenses as $expense) {
                    if ($expense['category_id'] == $budget['category_id']) {
                        $categorySpent += $expense['amount'];
                    }
                }
                if ($categorySpent > $budget['amount']) {
                    $overBudgetCategories[] = [
                        'category' => $budget['category_name'],
                        'budget' => $budget['amount'],
                        'spent' => $categorySpent,
                        'percentage' => ($categorySpent / $budget['amount']) * 100
                    ];
                }
            }
            ?>

            <!-- Chart for all categories -->
            <canvas id="categoryChart" class="mb-4"></canvas>

            <?php if (!empty($overBudgetCategories)): ?>
                <h6 class="text-danger mb-3">Over-Budget Categories:</h6>
                <?php foreach ($overBudgetCategories as $category): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span><?= htmlspecialchars($category['category']) ?></span>
                            <span class="text-danger">
                                <?= number_format($category['percentage'], 0) ?>% of budget
                            </span>
                        </div>
                        <div class="progress mt-1" style="height: 15px;">
                            <div class="progress-bar bg-danger" 
                                 role="progressbar" 
                                 style="width: <?= min($category['percentage'], 100) ?>%">
                                UGX <?= number_format($category['spent'], 0) ?> / UGX <?= number_format($category['budget'], 0) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-success">
                    All categories are within budget!
                </div>
            <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize budget chart
        <script>
      
        // Budget Chart
        const ctx = document.getElementById('budgetChart').getContext('2d');
        const budgetData = <?php 
            $chartData = [];
            foreach ($budgets as $budget) {
                $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE category = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$budget['category']]);
                $spent = $stmt->fetch()['total'];
                
                $chartData[] = [
                    'category' => $budget['category'],
                    'budget' => $budget['amount'],
                    'spent' => $spent
                ];
            }
            echo json_encode($chartData);
        ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: budgetData.map(item => item.category),
                datasets: [
                    {
                        label: 'Budget',
                        data: budgetData.map(item => item.budget),
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Spent',
                        data: budgetData.map(item => item.spent),
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgb(187, 99, 255)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '$' + context.raw.toFixed(2);
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = document.querySelector('input[name="amount"]').value;
            const date = document.querySelector('input[name="date"]').value;
            const category = document.querySelector('select[name="category"]').value;

            if (!amount || !date || !category) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return;
            }

            if (amount <= 0) {
                e.preventDefault();
                alert('Amount must be greater than 0');
                return;
            }

            const selectedDate = new Date(date);
            const today = new Date();
            if (selectedDate > today) {
                e.preventDefault();
                alert('Date cannot be in the future');
                return;
            }
        });

        // Format currency inputs
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = parseFloat(this.value).toFixed(2);
                }
            });
        });

        // Calculate and display totals
        function calculateTotals() {
            const totalBudget = budgetData.reduce((sum, item) => sum + item.budget, 0);
            const totalSpent = budgetData.reduce((sum, item) => sum + item.spent, 0);
            const remaining = totalBudget - totalSpent;

            const totalsHtml = `
                <div class="mt-4">
                    <h6>Summary</h6>
                    <p>Total Budget: $${totalBudget.toFixed(2)}</p>
                    <p>Total Spent: $${totalSpent.toFixed(2)}</p>
                    <p class="${remaining < 0 ? 'text-danger' : 'text-success'}">
                        Remaining: $${remaining.toFixed(2)}
                    </p>
                </div>
            `;

            document.querySelector('.card-body').insertAdjacentHTML('beforeend', totalsHtml);
        }

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Call calculate totals on page load
        calculateTotals();

        // Category spending chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryData = <?php 
        $chartData = [
            'labels' => [],
            'budgets' => [],
            'expenses' => []
        ];
        
        foreach ($budgets as $budget) {
            $categorySpent = 0;
            foreach ($expenses as $expense) {
                if ($expense['category_id'] == $budget['category_id']) {
                    $categorySpent += $expense['amount'];
                }
            }
            
            $chartData['labels'][] = $budget['category_name'];
            $chartData['budgets'][] = $budget['amount'];
            $chartData['expenses'][] = $categorySpent;
        }
        echo json_encode($chartData);
    ?>;

    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: categoryData.labels,
            datasets: [
                {
                    label: 'Budget',
                    data: categoryData.budgets,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Spent',
                    data: categoryData.expenses,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.raw.toFixed(2);
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>