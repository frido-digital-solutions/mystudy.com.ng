<?php
require_once __DIR__ . '/../config/config.php';
require_admin();
if (!admin_can('dashboard')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/');
}
header('Location: ' . SITE_URL . '/admin/dashboard.php');
exit;
?>
<i class="fas fa-book fa-3x opacity-50"></i>
                            </div>
                            <div class="mt-3">
                                <span class="me-2"><i class="fas fa-arrow-up"></i> 8%</span>
                                <span>vs last month</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Quizzes</h6>
                                    <h2 class="mb-0">124</h2>
                                </div>
                                <i class="fas fa-tasks fa-3x opacity-50"></i>
                            </div>
                            <div class="mt-3">
                                <span class="me-2"><i class="fas fa-arrow-up"></i> 5%</span>
                                <span>vs last month</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Active Now</h6>
                                    <h2 class="mb-0">42</h2>
                                </div>
                                <i class="fas fa-chart-line fa-3x opacity-50"></i>
                            </div>
                            <div class="mt-3">
                                <span class="me-2"><i class="fas fa-arrow-up"></i> 15%</span>
                                <span>vs yesterday</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Activity</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td data-label="#">1</td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/img/avatar1.jpg" alt="User" class="rounded-circle me-2" width="30" height="30">
                                            <span>John Doe</span>
                                        </div>
                                    </td>
                                    <td data-label="Activity">Uploaded a new study material</td>
                                    <td data-label="Time">2 min ago</td>
                                    <td data-label="Status"><span class="badge bg-success">Completed</span></td>
                                    <td data-label="Action">
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td data-label="#">2</td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/img/avatar2.jpg" alt="User" class="rounded-circle me-2" width="30" height="30">
                                            <span>Jane Smith</span>
                                        </div>
                                    </td>
                                    <td data-label="Activity">Completed a quiz</td>
                                    <td data-label="Time">15 min ago</td>
                                    <td data-label="Status"><span class="badge bg-success">Completed</span></td>
                                    <td data-label="Action">
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td data-label="#">3</td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/img/avatar3.jpg" alt="User" class="rounded-circle me-2" width="30" height="30">
                                            <span>Mike Johnson</span>
                                        </div>
                                    </td>
                                    <td data-label="Activity">Made a payment</td>
                                    <td data-label="Time">1 hour ago</td>
                                    <td data-label="Status"><span class="badge bg-warning text-dark">Pending</span></td>
                                    <td data-label="Action">
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td data-label="#">4</td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/img/avatar4.jpg" alt="User" class="rounded-circle me-2" width="30" height="30">
                                            <span>Sarah Williams</span>
                                        </div>
                                    </td>
                                    <td data-label="Activity">Requested a password reset</td>
                                    <td data-label="Time">3 hours ago</td>
                                    <td data-label="Status"><span class="badge bg-secondary">Cancelled</span></td>
                                    <td data-label="Action">
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td data-label="#">5</td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/img/avatar5.jpg" alt="User" class="rounded-circle me-2" width="30" height="30">
                                            <span>Tom Davis</span>
                                        </div>
                                    </td>
                                    <td data-label="Activity">New user registration</td>
                                    <td data-label="Time">5 hours ago</td>
                                    <td data-label="Status"><span class="badge bg-success">Completed</span></td>
                                    <td data-label="Action">
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">User Registration</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Traffic Sources</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trafficChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// User Registration Chart
const userCtx = document.getElementById('userChart').getContext('2d');
const userChart = new Chart(userCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [{
            label: 'New Users',
            data: [65, 59, 80, 81, 56, 55, 40],
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    display: true,
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Traffic Sources Chart
const trafficCtx = document.getElementById('trafficChart').getContext('2d');
const trafficChart = new Chart(trafficCtx, {
    type: 'doughnut',
    data: {
        labels: ['Direct', 'Referral', 'Social', 'Search', 'Email'],
        datasets: [{
            data: [300, 200, 150, 100, 50],
            backgroundColor: [
                'rgba(75, 192, 192, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.4)',
                'rgba(153, 102, 255, 0.8)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            }
        },
        cutout: '70%'
    }
});
</script>

<?php
// Include admin footer
include 'includes/footer.php';
?>
