<?php
include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {
    include_once(include_custom(realpath(__DIR__ . '/../../header.php'),false));
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                    <div class="col-12">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-uppercase text-muted mb-3">Status</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-xl-7 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="text-uppercase text-muted mb-3"><span class="icon-play"></span> Latest Media Updates</div>
                                            <table id="latestUpdatesMedia" class="table table-striped my-0">
                                                <thead>
                                                    <tr>
                                                        <th><?= L::contextmainSpeaker(); ?></th>
                                                        <th><?= L::agendaItem(); ?></th>
                                                        <th><?= L::lastChanged(); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-xl-5 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="text-uppercase text-muted mb-3"><span class="icon-tags"></span> Latest Entity Updates</div>
                                            <table id="latestUpdatesEntities" class="table table-striped my-0">
                                                <thead>
                                                    <tr>
                                                        <th><?= L::type(); ?></th>
                                                        <th><?= L::name(); ?></th>
                                                        <th><?= L::lastChanged(); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="text-uppercase text-muted mb-3">DEBUG</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('de');
    }

    // Function to fetch entities from API
    async function fetchEntities(type) {
        try {
            const response = await fetch(`<?= $config["dir"]["root"]; ?>/api/v1/?action=getItemsFromDB&itemType=${type}&id=all&limit=10&offset=0&sort=${type.charAt(0).toUpperCase() + type.slice(1)}LastChanged&order=desc`);
            const data = await response.json();
            //console.log(`API response for ${type}:`, data);
            // The getOverview functions return the data directly without a meta structure
            if (data && data.data) {
                const entities = data.data.map(entity => ({
                    id: entity[`${type.charAt(0).toUpperCase() + type.slice(1)}ID`],
                    label: entity[`${type.charAt(0).toUpperCase() + type.slice(1)}Label`],
                    type: type,
                    lastChanged: entity[`${type.charAt(0).toUpperCase() + type.slice(1)}LastChanged`]
                }));
                //console.log(`Processed entities for ${type}:`, entities);
                return entities;
            }
            return [];
        } catch (error) {
            console.error(`Error fetching ${type}:`, error);
            return [];
        }
    }

    // Function to fetch media from API
    async function fetchLatestMedia() {
        try {
            const response = await fetch(`<?= $config["dir"]["root"]; ?>/api/v1/search/media/?limit=5&sort=changed-desc`);
            const data = await response.json();
            
            if (data && data.data) {
                const mediaItems = data.data.map(media => {
                    // Extract agenda item title from relationships
                    const agendaItemTitle = media.relationships?.agendaItem?.data?.attributes?.title || 'Untitled';
                    
                    // Extract main speaker from relationships
                    const mainSpeaker = media.relationships?.people?.data?.[0]?.attributes?.label || 'Unknown';
                    
                    return {
                        id: media.id,
                        agendaItem: agendaItemTitle,
                        speaker: mainSpeaker,
                        lastChanged: media.attributes.lastChanged
                    };
                });
                
                // Update the table body
                const tbody = document.querySelector('#latestUpdatesMedia tbody');
                tbody.innerHTML = mediaItems.map(media => `
                    <tr>
                        <td>${media.speaker}</td>
                        <td><a href="<?= $config["dir"]["root"]; ?>/media/${media.id}">${media.agendaItem}</a></td>
                        <td>${formatDate(media.lastChanged)}</td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error fetching latest media:', error);
        }
    }

    // Function to fetch and display latest entities
    async function fetchLatestEntities() {
        try {
            // Fetch all entity types in parallel
            const [persons, organisations, documents, terms] = await Promise.all([
                fetchEntities('person'),
                fetchEntities('organisation'),
                fetchEntities('document'),
                fetchEntities('term')
            ]);

            // Combine all entities
            const allEntities = [...persons, ...organisations, ...documents, ...terms];

            // Sort by lastChanged date
            allEntities.sort((a, b) => new Date(b.lastChanged) - new Date(a.lastChanged));

            // Take only the latest 5
            const latestEntities = allEntities.slice(0, 5);

            // Update the table body
            const tbody = document.querySelector('#latestUpdatesEntities tbody');
            tbody.innerHTML = latestEntities.map(entity => `
                <tr>
                    <td><span class="icon-type-${entity.type}"></span><span class="visually-hidden">${entity.type}</span></td>
                    <td><a href="<?= $config["dir"]["root"]; ?>/${entity.type}/${entity.id}">${entity.label}</a></td>
                    <td>${formatDate(entity.lastChanged)}</td>
                </tr>
            `).join('');
        } catch (error) {
            console.error('Error fetching latest entities:', error);
        }
    }

    // Fetch latest entities and media when page loads
    fetchLatestEntities();
    fetchLatestMedia();
});
</script>

<?php
}
include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false));
?>