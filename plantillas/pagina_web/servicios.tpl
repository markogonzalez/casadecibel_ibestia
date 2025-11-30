<div role="main" class="main">
    <!-- Contenido -->
    <div class="container my-5">

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-5 mb-lg-0">
                <aside class="sidebar">
                    <h4 class="font-weight-semibold mb-4 text-color-quaternary">Categorias</h4>

                    <div class="accordion accordion-modern" id="accordionServicios">
                        {foreach $categorias as $cat}
                            {assign var="isOpen" value=($cat.id_categoria == $cat_abierta)}
                            <div class="accordion-item border-0 mb-2">
                                <h2 class="accordion-header" id="heading{$cat.id_categoria}">
                                    <button 
                                        class="accordion-button {if !$isOpen}collapsed{/if} text-color-quaternary bg-light"
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse{$cat.id_categoria}" 
                                        aria-expanded="{if $isOpen}true{else}false{/if}" 
                                        aria-controls="collapse{$cat.id_categoria}">
                                        {$cat.categoria}
                                    </button>
                                </h2>
                                <div 
                                    id="collapse{$cat.id_categoria}" 
                                    class="accordion-collapse collapse {if $isOpen}show{/if}" 
                                    aria-labelledby="heading{$cat.id_categoria}" 
                                    data-bs-parent="#accordionServicios"
                                >
                                    <div class="accordion-body p-3">
                                        <ul class="nav nav-list flex-column mb-0 ps-3">
                                            {foreach $cat.servicios as $srv}
                                                {assign var="isActive" value=($srv.slug == $item)}
                                                <li class="nav-item mb-2">
                                                    <a 
                                                        href="{$ruta_relativa}servicios/{$srv.slug}/" 
                                                        class="nav-link font-weight-bold text-3 p-0 border-0 {if $isActive}text-primary active{else}text-dark{/if} hover-underline-animation"
                                                    >
                                                        <i class="fas fa-angle-right me-2 {if $isActive}text-primary{else}text-muted{/if}"></i>{$srv.servicio}
                                                    </a>
                                                </li>
                                            {/foreach}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        {/foreach}
                    </div>
                </aside>
            </div>

            <!-- Contenido derecho -->
            <div class="col-lg-9">

                {if isset($servicio)}
                    <div class="feature-box feature-box-style-2 mb-5">
                        <div class="feature-box-info ps-0 ps-sm-3">
                            <h2 class="font-weight-semibold mb-3 text-color-primary">{$servicio.servicio}</h2>
                            <p class="lead font-weight-normal">{$servicio.descripcion}</p>

                            {if $servicio.imagen != ''}
                            <div class="col-md-8 col-sm-12" style="margin: 0 auto;">
                                <img src="{$ruta_relativa}img/servicios/{$servicio.imagen}" alt="{$servicio.servicio}" class="mb-4 mt-4 img-fluid box-shadow-custom"/>
                            </div>
                            {/if}

                        </div>
                    </div>
                {else}
                    <div class="row align-items-center bg-color-light p-4 rounded shadow-sm">
                        <div class="col-md-6 text-center mb-4 mb-md-0">
                            <img src="{$ruta_relativa}img/servicios.jpg" alt="Equipo Médico Intermédica" class="img-fluid rounded shadow">
                        </div>
                        <div class="col-md-6">
                            <h2 class="text-color-quaternary fw-bold mb-3">Cuidamos de ti con excelencia médica</h2>
                            <p class="lead mb-4">
                                En <strong>InterMédica</strong> contamos con especialistas de distintas áreas para brindarte 
                                diagnósticos precisos, tratamientos eficaces y un acompañamiento humano en cada etapa de tu salud.
                            </p>
                            <ul class="list list-icons list-icons-style-2 text-start mb-4">
                                <li><i class="fas fa-check text-primary"></i> Especialidades certificadas</li>
                                <li><i class="fas fa-check text-primary"></i> Equipos de última generación</li>
                                <li><i class="fas fa-check text-primary"></i> Atención humana y profesional</li>
                            </ul>
                        </div>
                    </div>
                {/if}

            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // Detectar si hay un servicio cargado
  const servicioActivo = document.querySelector(".feature-box-info");
  if (servicioActivo && window.innerWidth < 992) { // Solo en móvil/tablet
    // Esperar un pequeño delay para que el DOM y Bootstrap terminen
    setTimeout(() => {
      const header = document.querySelector(".header-body");
      const headerHeight = header ? header.offsetHeight : 80;

      const rect = servicioActivo.getBoundingClientRect();
      const offsetTop = window.pageYOffset + rect.top - headerHeight - 15;

      window.scrollTo({
        top: offsetTop,
        behavior: "smooth"
      });
    }, 600);
  }
});
</script>