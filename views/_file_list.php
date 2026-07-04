<?php /* File list partial — reusable across views */ ?>
      <section class="cv-section">
        <div class="cv-section-header">
          <h2 class="cv-section-title"><?= e($fileListTitle ?? 'Recent Files') ?></h2>
        </div>
        <div class="cv-card" x-show="filteredFiles.length || search">
          <div x-show="!filteredFiles.length && search" class="px-4 py-8 text-center text-cv-muted text-sm">
            Tidak ada hasil untuk "<span x-text="search" class="font-medium"></span>"
          </div>
          <template x-for="f in filteredFiles" :key="f.id">
            <div class="cv-file-row" @click="preview(f)">
              <div class="cv-file-icon" :class="'cv-ficon-' + f.kind" x-html="fileIconSvg(f.icon, 'w-5 h-5')"></div>
              <div class="cv-file-info">
                <span class="cv-file-name" x-text="f.name"></span>
                <span class="cv-file-cat" x-text="f.kind.charAt(0).toUpperCase() + f.kind.slice(1) + 's'"></span>
              </div>
              <span class="cv-file-date" x-text="f.created ? f.created.slice(0, 16).replace('T', ' ') : ''"></span>
              <span class="cv-file-size" x-text="f.size_h"></span>
              <div class="cv-file-actions flex gap-1" @click.stop>
                <button @click="toggleFavorite(f)" class="cv-dot-btn" :class="f.favorited && 'cv-star-active'" :title="f.favorited ? 'Unfavorite' : 'Favorite'">
                  <?= lucide('star', 'w-4 h-4') ?>
                </button>
                <button @click="shareFile(f)" class="cv-dot-btn" title="Share">
                  <?= lucide('link', 'w-4 h-4') ?>
                </button>
                <button @click="deleteFile(f)" class="cv-dot-btn" title="Delete">
                  <?= lucide('trash', 'w-4 h-4') ?>
                </button>
              </div>
            </div>
          </template>
        </div>
        <div x-show="!filteredFiles.length && !search" class="cv-card px-4 py-10 text-center text-cv-muted text-sm">
          Belum ada file. Upload pertamamu!
        </div>
      </section>
