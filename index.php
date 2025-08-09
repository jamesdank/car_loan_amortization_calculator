<?php
// ---------- PHP: amortization calculator as a function ----------
function calculateAmortization($loanAmount, $months, $annualInterestRate) {
    $loanAmount = max(0, floatval($loanAmount));
    $months = max(1, intval($months));
    $annualInterestRate = max(0, floatval($annualInterestRate));

    $monthlyRate = ($annualInterestRate / 100) / 12;

    if ($monthlyRate == 0) {
        $monthlyPayment = $loanAmount / $months;
    } else {
        $monthlyPayment = $loanAmount * $monthlyRate / (1 - pow(1 + $monthlyRate, -$months));
    }

    $schedule = [];
    $balance = $loanAmount;
    $totalInterest = 0;

    for ($i = 1; $i <= $months; $i++) {
        $interest = ($monthlyRate == 0) ? 0 : $balance * $monthlyRate;
        $principal = $monthlyPayment - $interest;

        // Fix last-row rounding so balance ends at 0
        if ($i === $months) {
            $principal = $balance;
            $monthlyPayment = $principal + $interest;
        }

        $balance -= $principal;
        $totalInterest += $interest;

        $schedule[] = [
            'month'     => $i,
            'payment'   => round($monthlyPayment, 2),
            'principal' => round($principal, 2),
            'interest'  => round($interest, 2),
            'balance'   => max(round($balance, 2), 0)
        ];
    }

    return [
        'monthlyPayment' => round($monthlyPayment, 2),
        'totalInterest'  => round($totalInterest, 2),
        'totalCost'      => round($loanAmount + $totalInterest, 2),
        'schedule'       => $schedule
    ];
}

// ---------- AJAX endpoint ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $loanAmount   = $_POST['loanAmount'] ?? 0;
    $months       = $_POST['months'] ?? 0;
    $interestRate = $_POST['interestRate'] ?? 0;

    header('Content-Type: application/json');
    echo json_encode(calculateAmortization($loanAmount, $months, $interestRate));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Car Loan Amortization Calculator</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  /* ==== Aesthetic background (new) ==== */
  :root{
    --blur-bg: rgba(255,255,255,0.55);
    --card-shadow: 0 10px 35px rgba(0,0,0,.10);
  }
  body{
    min-height: 100vh;
    margin: 0;
    background:
      radial-gradient(1200px 600px at 10% -10%, #eef5ff 0%, #eaf3ff 35%, transparent 36%),
      radial-gradient(900px 500px at 90% 10%, #fff2f5 0%, #ffeaf0 35%, transparent 36%),
      linear-gradient(140deg, #f7fbff 0%, #f2f6ff 30%, #f9f1ff 70%, #fef6f6 100%);
    backdrop-filter: saturate(120%);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Noto Color Emoji", sans-serif;
    padding-top: 56px;
  }
  .glass-card{
    background: var(--blur-bg);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 18px;
    box-shadow: var(--card-shadow);
    backdrop-filter: blur(10px);
  }
  .form-control, .btn{
    border-radius: 10px;
  }
  th{ background:#1f2937; color:#fff; position: sticky; top: 0; z-index: 1; }
  .scroll-area{
    max-height: 55vh;
    overflow: auto;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fff;
  }
  .badge-soft{
    background: rgba(31,41,55,.08);
    color: #111827;
    border: 1px solid rgba(31,41,55,.1);
  }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="glass-card p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="mb-0">Car Loan Amortization Calculator</h2>
          <span class="badge badge-soft">AJAX • Modal • CSV/PDF • Charts</span>
        </div>

        <form id="loanForm" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Loan Amount ($)</label>
            <input type="number" step="0.01" min="0" name="loanAmount" class="form-control" placeholder="e.g. 25000" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Loan Term (Months)</label>
            <input type="number" min="1" name="months" class="form-control" placeholder="e.g. 60" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Interest Rate (% APR)</label>
            <input type="number" step="0.01" min="0" name="interestRate" class="form-control" placeholder="e.g. 6.49" required>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary btn-lg px-4">
              Calculate
            </button>
          </div>
        </form>

        <div class="text-center mt-3 d-none" id="loadingState">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <div class="small text-muted mt-2">Crunching numbers…</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" aria-labelledby="resultsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resultsModalLabel">Amortization Results</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Summary -->
        <div class="row g-3 mb-2">
          <div class="col-md-4">
            <div class="glass-card p-3 h-100">
              <div class="text-muted small">Monthly Payment</div>
              <div class="fs-4 fw-semibold" id="monthlyPayment">$0.00</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="glass-card p-3 h-100">
              <div class="text-muted small">Total Interest</div>
              <div class="fs-4 fw-semibold" id="totalInterest">$0.00</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="glass-card p-3 h-100">
              <div class="text-muted small">Total Cost (Principal + Interest)</div>
              <div class="fs-4 fw-semibold" id="totalCost">$0.00</div>
            </div>
          </div>
        </div>

        <!-- Charts Toggle -->
        <div class="d-flex align-items-center justify-content-between mt-3">
          <h6 class="mb-0">Charts</h6>
          <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#chartsCollapse" aria-expanded="true" aria-controls="chartsCollapse">
            Toggle Charts
          </button>
        </div>

        <!-- Charts Area (default shown) -->
        <div class="collapse show" id="chartsCollapse">
          <div class="row g-3 mt-2">
            <div class="col-12">
              <div class="glass-card p-3">
                <canvas id="stackedBarChart" height="120"></canvas>
              </div>
            </div>
            <div class="col-12">
              <div class="glass-card p-3">
                <canvas id="balanceLineChart" height="120"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <h6 class="mt-4 mb-2">Amortization Schedule</h6>
        <div class="scroll-area">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:8%">Month</th>
                <th style="width:18%">Payment</th>
                <th style="width:18%">Principal</th>
                <th style="width:18%">Interest</th>
                <th style="width:18%">Remaining Balance</th>
              </tr>
            </thead>
            <tbody id="scheduleBody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button id="btnCsv" class="btn btn-success">Download CSV</button>
        <button id="btnPdf" class="btn btn-danger">Download PDF</button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- jsPDF + AutoTable for PDF export -->
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

<script>
  // Store last calculation for exports/charts
  let lastCalc = null;
  let stackedBarChart = null;
  let balanceLineChart = null;
  const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' });

  $("#loanForm").on("submit", function(e){
    e.preventDefault();
    $("#loadingState").removeClass("d-none");

    const payload = $(this).serialize() + "&ajax=1";

    $.post("index.php", payload)
      .done(function(res){
        const result = (typeof res === "string") ? JSON.parse(res) : res;
        lastCalc = result; // save for CSV/PDF/Charts

        // Summary
        $("#monthlyPayment").text(fmt.format(result.monthlyPayment));
        $("#totalInterest").text(fmt.format(result.totalInterest));
        $("#totalCost").text(fmt.format(result.totalCost));

        // Table
        let rows = "";
        result.schedule.forEach(row => {
          rows += `<tr>
            <td>${row.month}</td>
            <td>${fmt.format(row.payment)}</td>
            <td>${fmt.format(row.principal)}</td>
            <td>${fmt.format(row.interest)}</td>
            <td>${fmt.format(row.balance)}</td>
          </tr>`;
        });
        $("#scheduleBody").html(rows);

        // Charts
        renderCharts(result);

        // Modal
        const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
        modal.show();
      })
      .fail(function(){
        alert("Something went wrong. Please check your inputs and try again.");
      })
      .always(function(){
        $("#loadingState").addClass("d-none");
      });
  });

  function renderCharts(result){
    const labels = result.schedule.map(r => r.month);
    const principals = result.schedule.map(r => r.principal);
    const interests = result.schedule.map(r => r.interest);
    const balances = result.schedule.map(r => r.balance);

    // Destroy previous charts if exist
    if (stackedBarChart) { stackedBarChart.destroy(); }
    if (balanceLineChart) { balanceLineChart.destroy(); }

    // Stacked Bars: Principal vs Interest
    const ctxBar = document.getElementById('stackedBarChart').getContext('2d');
    stackedBarChart = new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Principal', data: principals, stack: 'stack1' },
          { label: 'Interest',  data: interests,  stack: 'stack1' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${fmt.format(ctx.parsed.y)}`
            }
          },
          legend: { position: 'top' },
          title: { display: true, text: 'Payment Composition (Stacked)' }
        },
        scales: {
          x: { stacked: true, title: { display: true, text: 'Month' } },
          y: { stacked: true, title: { display: true, text: 'Amount (USD)' } }
        }
      }
    });

    // Line: Remaining Balance
    const ctxLine = document.getElementById('balanceLineChart').getContext('2d');
    balanceLineChart = new Chart(ctxLine, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Remaining Balance',
          data: balances,
          tension: 0.25,
          pointRadius: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${fmt.format(ctx.parsed.y)}`
            }
          },
          legend: { position: 'top' },
          title: { display: true, text: 'Remaining Balance Over Time' }
        },
        scales: {
          x: { title: { display: true, text: 'Month' } },
          y: { title: { display: true, text: 'Amount (USD)' } }
        }
      }
    });
  }

  // ---------- CSV Download ----------
  $("#btnCsv").on("click", function(){
    if (!lastCalc) return;

    const headers = ["Month","Payment","Principal","Interest","Remaining Balance"];
    const lines = [headers.join(",")];

    lastCalc.schedule.forEach(r => {
      lines.push([
        r.month,
        r.payment.toFixed(2),
        r.principal.toFixed(2),
        r.interest.toFixed(2),
        r.balance.toFixed(2)
      ].join(","));
    });

    lines.push("");
    lines.push(["Monthly Payment", lastCalc.monthlyPayment.toFixed(2)].join(","));
    lines.push(["Total Interest", lastCalc.totalInterest.toFixed(2)].join(","));
    lines.push(["Total Cost", lastCalc.totalCost.toFixed(2)].join(","));

    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const ts = new Date().toISOString().slice(0,19).replace(/[:T]/g,"-");
    a.href = url;
    a.download = `amortization-${ts}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });

  // ---------- PDF Download ----------
  $("#btnPdf").on("click", async function(){
    if (!lastCalc) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape", unit: "pt", format: "a4" });

    const title = "Car Loan Amortization Schedule";
    const ts = new Date().toLocaleString();
    doc.setFontSize(16);
    doc.text(title, 40, 40);
    doc.setFontSize(10);
    doc.text(`Generated: ${ts}`, 40, 60);

    // Summary
    doc.setFontSize(12);
    doc.text(`Monthly Payment: ${fmt.format(lastCalc.monthlyPayment)}`, 40, 86);
    doc.text(`Total Interest:  ${fmt.format(lastCalc.totalInterest)}`, 40, 104);
    doc.text(`Total Cost:      ${fmt.format(lastCalc.totalCost)}`, 40, 122);

    // Table data
    const columns = [
      { header: "Month", dataKey: "month" },
      { header: "Payment", dataKey: "payment" },
      { header: "Principal", dataKey: "principal" },
      { header: "Interest", dataKey: "interest" },
      { header: "Remaining Balance", dataKey: "balance" }
    ];
    const data = lastCalc.schedule.map(r => ({
      month: r.month,
      payment: fmt.format(r.payment),
      principal: fmt.format(r.principal),
      interest: fmt.format(r.interest),
      balance: fmt.format(r.balance),
    }));

    doc.autoTable({
      startY: 150,
      head: [columns.map(c => c.header)],
      body: data.map(row => columns.map(c => row[c.dataKey])),
      styles: { fontSize: 9, cellPadding: 4, overflow: "linebreak" },
      headStyles: { fillColor: [31, 41, 55], textColor: [255,255,255] },
      margin: { left: 40, right: 40 },
      didDrawPage: (data) => {
        const pageCount = doc.getNumberOfPages();
        const str = `Page ${data.pageNumber} of ${pageCount}`;
        doc.setFontSize(9);
        doc.text(str, doc.internal.pageSize.getWidth() - 80, doc.internal.pageSize.getHeight() - 20);
      }
    });

    const filenameTs = new Date().toISOString().slice(0,19).replace(/[:T]/g,"-");
    doc.save(`amortization-${filenameTs}.pdf`);
  });
</script>
</body>
</html>
