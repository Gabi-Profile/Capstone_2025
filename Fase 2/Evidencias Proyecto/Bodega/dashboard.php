
<?php
session_start();
require 'db.php';
include 'funciones.php';

$filtros = [
    'especialidad' => $_GET['especialidad'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

$insumosBajos = obtenerInsumosBajoStock();
$datosPorMes = obtenerDatosAgrupadosPorMes($conn, $filtros);
$datosHeatmap = obtenerDatosParaHeatmap($conn, $filtros);
$datosPorMes = obtenerDatosAgrupadosPorMes($conn, $filtros);
$topEspecialidad = obtenerTopInsumosPorEspecialidad($conn, $filtros);

// Generar lista completa de insumos por especialidad
// ➕ Generar todos los insumos por especialidad (para el panel dinámico)
$where = [];
$params = [];
$tipos = '';

if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
    $where[] = "fecha_sol BETWEEN ? AND ?";
    $params[] = $filtros['fecha_inicio'];
    $params[] = $filtros['fecha_fin'];
    $tipos .= 'ss';
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $conn->prepare("SELECT fecha_sol, insumos FROM cirugias $where_sql");
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Mapa de especialidad
$componentes = $conn->query("SELECT insumo, especialidad FROM componentes")->fetch_all(MYSQLI_ASSOC);
$mapa_especialidad = [];
foreach ($componentes as $c) {
    $insumo = trim($c['insumo'] ?? '');
    $especialidad = $c['especialidad'] ?? 'Sin asignar';
    $mapa_especialidad[$insumo] = $especialidad;
}

$insumosPorEspecialidad = [];

while ($row = $result->fetch_assoc()) {
    $insumos = explode(',', $row['insumos']);
    foreach ($insumos as $item) {
        $item = trim($item);

        if (preg_match('/(.*?)\s*\(x?(\d+)\)/i', $item, $matches)) {
            $nombre = trim($matches[1]);
            $cantidad = (int)$matches[2];
        } else {
            $nombre = $item;
            $cantidad = 1;
        }

        $especialidad = $mapa_especialidad[$nombre] ?? 'Sin asignar';

        $insumosPorEspecialidad[] = [
            'especialidad' => $especialidad,
            'insumo' => $nombre,
            'stock' => $cantidad
        ];
    }
}



// Consultas para el dashboard 
$total_insumos = $conn->query("SELECT COUNT(*) FROM componentes")->fetch_row()[0];
$stock_critico = $conn->query("SELECT COUNT(*) FROM componentes WHERE stock <= 5")->fetch_row()[0];
$ultimas_reposiciones = $conn->query("SELECT * FROM movimientos WHERE tipo = 'entrada' ORDER BY fecha DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

//actualizar la tabla de ultimas reposiciones

// Construye condiciones WHERE basadas en los filtros
$whereConditions = [];
$params = [];

// Filtro por especialidad
if (!empty($_GET['especialidad'])) {
    $whereConditions[] = "c.especialidad = ?";
    $params[] = $_GET['especialidad'];
}

// Filtro por fecha
if (!empty($_GET['fecha_inicio']) && !empty($_GET['fecha_fin'])) {
    $whereConditions[] = "m.fecha BETWEEN ? AND ?";
    $params[] = $_GET['fecha_inicio'];
    $params[] = $_GET['fecha_fin'];
}



// Consulta modificada para el gráfico de consumo
$sqlConsumo = "
    SELECT c.especialidad, SUM(m.cantidad) as total 
    FROM movimientos m
    JOIN componentes c ON m.componente_id = c.id
    WHERE m.tipo = 'salida'
";

// Añade condiciones WHERE si existen
if (!empty($whereConditions)) {
    $sqlConsumo .= " AND " . implode(" AND ", $whereConditions);
}

$sqlConsumo .= " GROUP BY c.especialidad";

// Prepara y ejecuta la consulta
$stmt = $conn->prepare($sqlConsumo);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$consumo_especialidad = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MediTrack</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/regression@2.0.1/dist/regression.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chroma-js/2.1.0/chroma.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
function generarPDF() {
  capturarGraficos(() => {
    const elemento = document.getElementById('reporte');
    const opciones = {
      margin: 0.5,
      filename: 'reporte_insumos_mediTrack.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().from(elemento).set(opciones).save();
  });
}
</script>


    <style>
                .card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            
        }
        .card { transform: translateY(-5px); }
        .grid-3-col {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 20px;
        }
        .card-header {
        padding: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
        }

        .card-header i {
            font-size: 1.2em;
        }
        /* Agrega esto en tu sección de estilos CSS */
        .card.mb-4 {
            margin-bottom: 1.7rem !important; /* 32px si estás usando Bootstrap base 16px */
        }

        .kpi-number {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .grid-2-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .grid-3-col, .grid-2-col {
                grid-template-columns: 1fr;
            }
        }
        /* Filtros */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            margin-bottom: 12px; /* Aumentado de 5px */
            font-weight: 500;
            margin-top: 12px;
        }

        .date-range {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-range span {
            color: #666;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-self: flex-end;
        }

        .btn-filter {
            background-color: #4e73df;
            color: white;
        }

        .btn-clear {
            background-color: #e74a3b;
            color: white;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
/* Forzar que el card-body no se expanda más allá de su tamaño original */
.card-torta-container {
  display: flex;
  flex-wrap: nowrap;
  gap: 20px;
  width: 100%;
  overflow: hidden;
}

#graficoTortaContenedor {
  flex: 0 0 60%;
  max-width: 60%;
}


.detalle-especialidad {
  display: none;
  flex: 0 0 40%;
  padding-left: 10px;
}


.detalle-especialidad.mostrar {
  display: block;
}


</style>
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Gestion de insumos medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='bodega.php'">Volver</button>
        </div>
    </div>
</head>
<body>
    
    <div class="container">
        <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter"></i> Filtros Avanzados
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="especialidad"><i class="fas fa-tag"></i> Especialidad:</label>
                        <select name="especialidad" id="especialidad" class="form-control">
                            <option value="">Todas las áreas</option>
                            <?php
                            $especialidades = $conn->query("SELECT DISTINCT especialidad FROM componentes");
                            while ($esp = $especialidades->fetch_assoc()):
                                $selected = ($_GET['especialidad'] ?? '') == $esp['especialidad'] ? 'selected' : '';
                            ?>
                                <option value="<?= $esp['especialidad'] ?>" <?= $selected ?>>
                                    <?= ucfirst($esp['especialidad']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <!-- Filtro por Fecha -->
                    <div class="filter-group">
                        <label for="fecha_inicio"><i class="fas fa-calendar-alt"></i> Rango de Fechas:</label>
                        <div class="date-range">
                            <input type="date" name="fecha_inicio" id="fecha_inicio" 
                                   value="<?= $_GET['fecha_inicio'] ?? '' ?>" class="form-control">
                            <span>a</span>
                            <input type="date" name="fecha_fin" id="fecha_fin" 
                                   value="<?= $_GET['fecha_fin'] ?? '' ?>" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Botones -->
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                        <a href="dashboard.php" class="btn btn-clear">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
        <!-- Sección de KPIs -->
        <div class="grid-3-col">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-boxes"></i> Total Insumos
                </div>
                <div class="card-body text-center">
                    <div class="kpi-number text-primary"><?= $total_insumos ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-warning">
                    <i class="fas fa-exclamation-triangle"></i> Stock Crítico
                </div>
                <div class="card-body text-center">
                    <div class="kpi-number text-warning"><?= $stock_critico ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-success">
                    <i class="fas fa-history"></i> Última Reposición
                </div>
                <div class="card-body text-center">
                    <div class="kpi-number text-success">
                        <?= !empty($ultimas_reposiciones) ? date('d/m/Y', strtotime($ultimas_reposiciones[0]['fecha'])) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid-2-col">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Predicción Insumos
                </div>
                <div class="card-body text-center">
                    <canvas id="graficoPrediccionTodos" ></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Top insumos más solicitados
                </div>
                <div class="card-body text-center">
            <canvas id="graficoTopInsumosMes"></canvas>
                </div>
            </div>

            <div class="card">
  <div class="card-header">
    <i class="fas fa-chart-pie"></i> Insumos según especialidad
  </div>
  <div class="card-body card-torta-container">
    <div id="graficoTortaContenedor">
      <canvas id="graficoTortaEspecialidad"></canvas>
    </div>
    <div id="detalleEspecialidad" class="detalle-especialidad">
      <h4 id="tituloPanel">Insumos</h4>
      <ul id="listaDetalle" class="list-group"></ul>
    </div>
  </div>
</div>


            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Mapa de calor
                </div>
                <div class="card-body text-center">
                    <canvas id="graficoHeatmap"></canvas>
                </div>
            </div>
        </div>
<button onclick="generarPDF()" class="btn btn-primary" style="margin-bottom: 20px; width: 200px;">
    Descargar reporte PDF
</button>



        <!-- Tabla de Insumos Críticos -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-circle"></i> Insumos con Stock Crítico
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Código</th>
                                <th>Stock</th>
                                <th>Ubicación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($insumosBajos)): ?>
                                <?php foreach ($insumosBajos as $insumo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($insumo['insumo']) ?></td>
                                    <td><?= htmlspecialchars($insumo['codigo']) ?></td>
                                    <td class="text-danger font-weight-bold"><?= $insumo['stock'] ?></td>
                                    <td><?= htmlspecialchars($insumo['ubicacion']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-success">
                                        <i class="fas fa-check-circle"></i> No hay insumos con stock crítico
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

   <script>
  const insumosPorEspecialidad = <?= json_encode($insumosPorEspecialidad) ?>;


    // Función para mostrar/ocultar el menú de usuario
    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }

    // Cerrar el menú al hacer clic fuera de él
    document.addEventListener('click', function(event) {
        const accountBtn = document.getElementById('cuenta-btn');
        const accountInfo = document.getElementById('accountInfo');
        
        if (!accountBtn.contains(event.target) && !accountInfo.contains(event.target)){
            accountInfo.style.display = 'none';
        }
    });

    const datos = <?= json_encode($datosPorMes) ?>;

    // Agrupar por insumo (sumar cantidades de todos los meses)
    const resumen = {};
    datos.forEach(d => {
        if (!resumen[d.insumo]) resumen[d.insumo] = 0;
        resumen[d.insumo] += d.cantidad;
    });

    // Obtener los 10 más usados
    const top10 = Object.entries(resumen)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 10);

    const etiquetas = top10.map(([nombre]) => nombre);
    const cantidades = top10.map(([_, cantidad]) => cantidad);

    new Chart(document.getElementById('graficoTopInsumosMes'), {
        type: 'bar',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Total consumido',
                data: cantidades,
                backgroundColor: 'rgba(54, 162, 235, 0.7)'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: '10 Insumos más usados (total)'
                }
            }
        }
    });


document.addEventListener('DOMContentLoaded', function () {
  const raw = <?= json_encode($datosHeatmap) ?>;

  const insumos = [...new Set(raw.map(r => r.insumo))];
  const meses = [...new Set(raw.map(r => r.mes))].sort();

  const matriz = insumos.map(ins => {
    return meses.map(m => {
      const item = raw.find(r => r.insumo === ins && r.mes === m);
      return item ? item.cantidad : 0;
    });
  });

  const maxValor = Math.max(...matriz.flat());
  const escala = chroma.scale(['#d4f0ff', '#2b8cbe', '#084081']).domain([0, maxValor]);

  const datasets = insumos.map((insumo, i) => ({
    label: insumo,
    data: matriz[i],
    backgroundColor: matriz[i].map(val => escala(val).alpha(0.9).css()),
    borderWidth: 1
  }));

  new Chart(document.getElementById('graficoHeatmap'), {
    type: 'bar',
    data: {
      labels: meses,
      datasets: datasets
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: 'Consumo mensual por insumo'
        },
        legend: { display: false }
      },
      scales: {
        x: { stacked: true },
        y: { stacked: true }
      }
    }
  });
});

// Usamos datos ya disponibles
const agrupados = datos;

// Agrupar por insumo
const agrupadoPorInsumo = {};
agrupados.forEach((d) => {
  if (!agrupadoPorInsumo[d.insumo]) agrupadoPorInsumo[d.insumo] = [];
  agrupadoPorInsumo[d.insumo].push(d);
});

const prediccionesFinales = [];

// Recorremos cada insumo para aplicar regresión
Object.keys(agrupadoPorInsumo).forEach((insumo) => {
  const registros = agrupadoPorInsumo[insumo];
  if (registros.length < 3) return; // omitir si hay pocos datos

  registros.sort((a, b) => a.mes.localeCompare(b.mes));
  const puntos = registros.map((d, i) => [i, d.cantidad]);

  const resultado = regression.linear(puntos);
  const valor = resultado.predict(registros.length)[1]; // predicción próximo mes
  prediccionesFinales.push({ insumo, cantidad: Math.round(valor) });
});

// Ordenar por cantidad descendente
prediccionesFinales.sort((a, b) => b.cantidad - a.cantidad);

// Separar etiquetas y valores
const etiquetasPred = prediccionesFinales.map((d) => d.insumo);
const cantidadesPred = prediccionesFinales.map((d) => d.cantidad);

// Graficar
new Chart(document.getElementById('graficoPrediccionTodos'), {
  type: 'bar',
  data: {
    labels: etiquetasPred,
    datasets: [{
      label: 'Cantidad estimada (próximo mes)',
      data: cantidadesPred,
      backgroundColor: 'rgba(0, 123, 255, 0.7)'
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: {
      title: {
        display: true,
        text: 'Predicción general de insumos para el próximo mes'
      }
    }
  }
});

const topEspecialidad = <?= json_encode($topEspecialidad) ?>;
const etiquetasPie = topEspecialidad.map(e => e.especialidad);
const valoresPie = topEspecialidad.map(e => e.cantidad);
const detalles = topEspecialidad.map(e => e.insumo);

const pieChart = new Chart(document.getElementById('graficoTortaEspecialidad'), {
  type: 'pie',
  data: {
    labels: etiquetasPie,
    datasets: [{
      data: valoresPie,
      backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#db88f8']
    }]
  },
  options: {
    responsive: true,
    plugins: {
      title: {
        display: true,
        text: 'Insumo más usado por especialidad'
      }
    }
  }
});

document.getElementById('graficoTortaEspecialidad').onclick = function(evt) {
  const activePoints = pieChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
  if (activePoints.length > 0) {
    const index = activePoints[0].index;
    const especialidadSeleccionada = etiquetasPie[index];

    document.getElementById('tituloPanel').textContent = `Insumos: ${especialidadSeleccionada}`;
    const detalle = insumosPorEspecialidad.filter(i => i.especialidad === especialidadSeleccionada);
    const lista = document.getElementById('listaDetalle');
    lista.innerHTML = '';
    detalle.forEach(item => {
      const li = document.createElement('li');
      li.className = 'list-group-item';
      li.innerHTML = `<strong>${item.insumo}</strong><br><span>Stock actual: ${item.stock}</span>`;
      lista.appendChild(li);
    });

    document.getElementById('detalleEspecialidad').classList.add('mostrar');
  }
};
function capturarGraficos(callback) {
  const charts = [
    { id: 'graficoPrediccionTodos', imgId: 'imgPrediccion' },
    { id: 'graficoTopInsumosMes', imgId: 'imgTop' },
    { id: 'graficoTortaEspecialidad', imgId: 'imgTorta' },
    { id: 'graficoHeatmap', imgId: 'imgHeatmap' }
  ];

  charts.forEach((g, index) => {
    const canvas = document.getElementById(g.id);
    const img = document.getElementById(g.imgId);
    if (canvas && img) {
      img.src = canvas.toDataURL();
    }
  });

  setTimeout(callback, 500); // espera medio segundo para asegurar la carga
}

function generarPDF() {
  // Mostrar el div temporalmente (oculto antes)
  const elemento = document.getElementById('reporte');
  elemento.style.display = 'block';

  capturarGraficos(() => {
    const opciones = {
      margin: 0.5,
      filename: 'reporte_insumos_mediTrack.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().from(elemento).set(opciones).save().then(() => {
      elemento.style.display = 'none'; // volver a ocultar después de generar
    });
  });
}



</script>
<div id="reporte" style="display: none; padding: 20px; font-family: Arial, sans-serif; background: white; color: #333;">
    <!-- ENCABEZADO -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
        <img src="asset/logo.png" alt="Logo" style="height: 60px;">
        <div>
            <h2 style="margin: 0;">Reporte de Gestión de Insumos Médicos</h2>
            <h4 style="margin: 0; font-weight: normal;">Hospital Clínico Félix Bulnes</h4>
            <small>Fecha de generación: <?= date('d/m/Y H:i') ?></small>
        </div>
    </div>

    <!-- FILTROS -->
    <div style="margin-bottom: 20px;">
        <strong>Filtros aplicados:</strong><br>
        <ul style="margin-top: 5px;">
            <li><strong>Especialidad:</strong> <?= $filtros['especialidad'] ?: 'Todas' ?></li>
            <li><strong>Rango de fechas:</strong> 
                <?= $filtros['fecha_inicio'] && $filtros['fecha_fin'] ? "{$filtros['fecha_inicio']} a {$filtros['fecha_fin']}" : 'Sin filtrar' ?>
            </li>
        </ul>
    </div>

    <!-- KPIs -->
    <div style="display: flex; gap: 30px; margin-bottom: 30px;">
        <div>
            <div style="font-weight: bold;">Total de Insumos</div>
            <div style="font-size: 24px; color: #007bff;"><?= $total_insumos ?></div>
        </div>
        <div>
            <div style="font-weight: bold;">Insumos en stock crítico</div>
            <div style="font-size: 24px; color: #dc3545;"><?= $stock_critico ?></div>
        </div>
        <div>
            <div style="font-weight: bold;">Última reposición</div>
            <div style="font-size: 24px; color: #28a745;">
                <?= !empty($ultimas_reposiciones) ? date('d/m/Y', strtotime($ultimas_reposiciones[0]['fecha'])) : 'N/A' ?>
            </div>
        </div>
    </div>

    <!-- GRÁFICOS COMO IMAGENES -->
    <div style="margin-bottom: 40px;">
        <h4>Predicción de insumos</h4>
        <img src="" id="imgPrediccion" style="width: 100%; border: 1px solid #ccc;">
    </div>

    <div style="margin-bottom: 40px;">
        <h4>Top insumos más solicitados</h4>
        <img src="" id="imgTop" style="width: 100%; border: 1px solid #ccc;">
    </div>

    <div style="margin-bottom: 40px;">
        <h4>Insumos según especialidad</h4>
        <img src="" id="imgTorta" style="width: 100%; border: 1px solid #ccc;">
    </div>

    <div style="margin-bottom: 40px;">
        <h4>Mapa de calor (uso mensual)</h4>
        <img src="" id="imgHeatmap" style="width: 100%; border: 1px solid #ccc;">
    </div>

    <!-- TABLA -->
    <h4>Insumos con Stock Crítico</h4>
    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
        <thead>
            <tr style="background: #f0f0f0;">
                <th style="padding: 8px; border: 1px solid #ccc;">Insumo</th>
                <th style="padding: 8px; border: 1px solid #ccc;">Código</th>
                <th style="padding: 8px; border: 1px solid #ccc;">Stock</th>
                <th style="padding: 8px; border: 1px solid #ccc;">Ubicación</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($insumosBajos)): ?>
                <?php foreach ($insumosBajos as $insumo): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ccc;"><?= htmlspecialchars($insumo['insumo']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ccc;"><?= htmlspecialchars($insumo['codigo']) ?></td>
                    <td style="padding: 8px; border: 1px solid #ccc;"><?= $insumo['stock'] ?></td>
                    <td style="padding: 8px; border: 1px solid #ccc;"><?= htmlspecialchars($insumo['ubicacion']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 8px;">No hay insumos con stock crítico.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>