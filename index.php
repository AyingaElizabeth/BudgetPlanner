<?php
// index.php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$categories = getCategories($pdo, $user_id);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $_POST['category'],
            $_POST['amount'],
            $_POST['description'],
            $_POST['date']
        ]);
    }
    
    if (isset($_POST['set_budget'])) {
        $stmt = $pdo->prepare("INSERT INTO budgets (user_id, category_id, amount, month) 
                              VALUES (?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE amount = ?");
        $stmt->execute([
            $user_id,
            $_POST['budget_category'],
            $_POST['budget_amount'],
            date('Y-m-01'), // First day of current month
            $_POST['budget_amount']
        ]);
    }
}

// Get expenses and budgets
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT e.*, c.name as category_name 
    FROM expenses e 
    JOIN categories c ON e.category_id = c.id 
    WHERE e.user_id = ? AND DATE_FORMAT(e.date, '%Y-%m') = ?
    ORDER BY e.date DESC
");
$stmt->execute([$user_id, $current_month]);
$expenses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name 
    FROM budgets b 
    JOIN categories c ON b.category_id = c.id 
    WHERE b.user_id = ? AND DATE_FORMAT(b.month, '%Y-%m') = ?
");
$stmt->execute([$user_id, $current_month]);
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

        <div class="row">
            <!-- Expenses List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Expenses</h5>
                        <div>
                            <input type="month" id="monthFilter" class="form-control" value="<?= date('Y-m') ?>">
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
                                        <td>$<?= number_format($expense['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($expense['description']) ?></td>
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
            </div>

            <!-- Budget Overview -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Budget Overview</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-4">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select name="budget_category" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="budget_amount" class="form-control" placeholder="Amount" required step="0.01">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="set_budget" class="btn btn-success mt-2">Set Budget</button>
                        </form>
                        <canvas id="budgetChart"></canvas>
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
    </script>
</body>
</html>