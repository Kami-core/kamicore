<!-- kami:template browser -->
<section class="admin-page media-page">
  <header class="admin-page-header">
    <div>
      <h2 class="admin-page-title">{{phrase.media}}</h2>
      <p class="admin-page-description">{{phrase.media_help}}</p>
    </div>
  </header>

  <div
    class="media-browser"
    data-media-browser
    data-root="{{root}}"
    data-can-manage="{{can_manage}}"
    data-list-url="{{list_url}}"
    data-upload-url="{{upload_url}}"
    data-mkdir-url="{{mkdir_url}}"
    data-rename-url="{{rename_url}}"
    data-move-url="{{move_url}}"
    data-delete-url="{{delete_url}}"
  >
    <div class="media-browser-toolbar">
      <div class="media-browser-actions">
        <button class="admin-button admin-button-primary" type="button" data-media-upload>{{phrase.upload}}</button>
        <button class="admin-button admin-button-secondary" type="button" data-media-mkdir>{{phrase.new_folder}}</button>
        <button class="admin-button admin-button-secondary" type="button" data-media-refresh>{{phrase.refresh}}</button>
        <input type="file" hidden multiple data-media-file-input>
      </div>
      <div class="media-browser-path" data-media-breadcrumbs></div>
    </div>

    <div class="media-browser-notice" data-media-notice hidden></div>
    <div class="media-browser-grid" data-media-grid aria-live="polite"></div>
    <div class="media-browser-empty" data-media-empty hidden>{{phrase.empty_folder}}</div>
  </div>

</section>
<!-- /kami:template -->
