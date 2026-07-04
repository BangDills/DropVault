<?php /* Notes section partial */ ?>
      <section x-show="filteredNotes.length" class="cv-section">
        <div class="cv-section-header"><h2 class="cv-section-title">Notes</h2></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <template x-for="n in filteredNotes" :key="'n'+n.id">
            <div class="cv-card card-hover group relative p-4 cursor-pointer" @click="openNote(n)">
              <div class="flex items-start gap-2.5 mb-2">
                <span class="text-cv-accent mt-0.5 shrink-0"><?= lucide('note', 'w-[18px] h-[18px]') ?></span>
                <p class="text-sm font-semibold truncate flex-1" x-text="n.title"></p>
              </div>
              <p class="text-xs text-cv-muted line-clamp-4 whitespace-pre-wrap break-words [&_a]:text-cv-accent [&_a]:underline" x-html="n.html"></p>
              <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition">
                <button @click.stop="deleteNote(n)" class="p-1.5 rounded-lg text-cv-muted hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition" title="Hapus">
                  <?= lucide('trash', 'w-4 h-4') ?>
                </button>
              </div>
            </div>
          </template>
        </div>
      </section>
