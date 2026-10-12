<?php
/**
 * Booking form markup. All data arrives from the REST API at runtime.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sbk" x-data="sbkBooking()" x-cloak>

	<!-- Progress indicator, visible on every step -->
	<ol class="sbk-progress" :aria-label="'<?php echo esc_js( __( 'Βήματα κράτησης', 'simple-booking' ) ); ?>'">
		<template x-for="(label, i) in steps" :key="i">
			<li class="sbk-progress__item"
				:class="stepClasses(i)"
				:aria-current="isCurrent(i) ? 'step' : false">
				<button type="button" class="sbk-progress__btn"
					:disabled="!isClickable(i)"
					@click="goTo(i + 1)">
					<span class="sbk-progress__num" x-text="stepMarker(i)"></span>
					<span class="sbk-progress__label" x-text="label"></span>
				</button>
			</li>
		</template>
	</ol>

	<div class="sbk-error" x-show="error" x-transition role="alert" aria-live="assertive">
		<span x-text="error"></span>
	</div>

	<div class="sbk-panel" :aria-busy="loading">

		<!-- ── Step 1: staff ─────────────────────────────────────────────── -->
		<section x-show="isStep(1)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Διάλεξε κουρέα', 'simple-booking' ); ?></h2>

			<p class="sbk-empty" x-show="!loading && staff.length === 0">
				<?php esc_html_e( 'Δεν υπάρχουν διαθέσιμοι κουρείς αυτή τη στιγμή.', 'simple-booking' ); ?>
			</p>

			<div class="sbk-cards">
				<template x-for="member in staff" :key="member.id">
					<button type="button" class="sbk-card"
						:class="{ 'is-selected': selected.staff?.id === member.id }"
						:aria-pressed="selected.staff?.id === member.id"
						@click="pickStaff(member)">
						<span class="sbk-card__avatar" :style="member.color ? 'background:' + member.color : ''">
							<template x-if="member.avatar">
								<img :src="member.avatar" :alt="member.name" loading="lazy">
							</template>
							<template x-if="!member.avatar">
								<span x-text="member.name.charAt(0)"></span>
							</template>
						</span>
						<span class="sbk-card__body">
							<span class="sbk-card__title" x-text="member.name"></span>
							<span class="sbk-card__meta" x-show="member.bio" x-text="member.bio"></span>
						</span>
					</button>
				</template>
			</div>
		</section>

		<!-- ── Step 2: service ───────────────────────────────────────────── -->
		<section x-show="isStep(2)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Διάλεξε υπηρεσία', 'simple-booking' ); ?></h2>

			<p class="sbk-empty" x-show="!loading && services.length === 0">
				<?php esc_html_e( 'Αυτό το άτομο δεν έχει διαθέσιμες υπηρεσίες.', 'simple-booking' ); ?>
			</p>

			<div class="sbk-list">
				<template x-for="service in services" :key="service.id">
					<button type="button" class="sbk-row"
						:class="{ 'is-selected': selected.service?.id === service.id }"
						:aria-pressed="selected.service?.id === service.id"
						@click="pickService(service)">
						<span class="sbk-row__main">
							<span class="sbk-row__title" x-text="service.name"></span>
							<span class="sbk-row__meta">
								<span x-text="service.duration_label"></span>
								<template x-if="service.category">
									<span class="sbk-tag" x-text="service.category"></span>
								</template>
							</span>
							<span class="sbk-row__desc" x-show="service.description" x-text="service.description"></span>
						</span>
						<span class="sbk-row__price" x-text="service.price_label"></span>
					</button>
				</template>
			</div>
		</section>

		<!-- ── Step 3: extras ────────────────────────────────────────────── -->
		<section x-show="isStep(3)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Θέλεις κάτι επιπλέον;', 'simple-booking' ); ?></h2>
			<p class="sbk-sub"><?php esc_html_e( 'Προαιρετικό — μπορείς να προχωρήσεις χωρίς επιλογή.', 'simple-booking' ); ?></p>

			<p class="sbk-empty" x-show="!loading && extras.length === 0">
				<?php esc_html_e( 'Δεν υπάρχουν extras για αυτή την υπηρεσία.', 'simple-booking' ); ?>
			</p>

			<div class="sbk-list">
				<template x-for="extra in extras" :key="extra.id">
					<label class="sbk-row sbk-row--check" :class="{ 'is-selected': hasExtra(extra.id) }">
						<input type="checkbox" class="sbk-check"
							:checked="hasExtra(extra.id)"
							@change="toggleExtra(extra)">
						<span class="sbk-row__main">
							<span class="sbk-row__title" x-text="extra.name"></span>
							<span class="sbk-row__meta">
								<span x-text="'+' + extra.duration_label"></span>
							</span>
							<span class="sbk-row__desc" x-show="extra.description" x-text="extra.description"></span>
						</span>
						<span class="sbk-row__price" x-text="'+' + extra.price_label"></span>
					</label>
				</template>
			</div>
		</section>

		<!-- ── Step 4: date and time ─────────────────────────────────────── -->
		<section x-show="isStep(4)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Διάλεξε ημερομηνία και ώρα', 'simple-booking' ); ?></h2>

			<div class="sbk-calendar">
				<div class="sbk-calendar__head">
					<button type="button" class="sbk-nav" @click="prevMonth()" :disabled="!canGoPrev()"
						aria-label="<?php esc_attr_e( 'Προηγούμενος μήνας', 'simple-booking' ); ?>">‹</button>
					<span class="sbk-calendar__title" x-text="monthTitle()" aria-live="polite"></span>
					<button type="button" class="sbk-nav" @click="nextMonth()" :disabled="!canGoNext()"
						aria-label="<?php esc_attr_e( 'Επόμενος μήνας', 'simple-booking' ); ?>">›</button>
				</div>

				<div class="sbk-calendar__grid sbk-calendar__grid--head" aria-hidden="true">
					<template x-for="wd in weekdaysShort" :key="wd">
						<span class="sbk-calendar__wd" x-text="wd"></span>
					</template>
				</div>

				<div class="sbk-calendar__grid">
					<template x-for="cell in calendarCells()" :key="cell.key">
						<template x-if="cell.date">
							<button type="button" class="sbk-day"
								:class="{ 'is-available': cell.available, 'is-selected': selected.date === cell.date }"
								:disabled="!cell.available"
								:aria-label="cell.date"
								@click="pickDate(cell.date)">
								<span x-text="cell.day"></span>
							</button>
						</template>
						<template x-if="!cell.date">
							<span class="sbk-day sbk-day--empty"></span>
						</template>
					</template>
				</div>
			</div>

			<div class="sbk-slots" x-show="selected.date">
				<h3 class="sbk-h3" x-text="selectedDateLabel()"></h3>

				<p class="sbk-empty" x-show="!loading && slots.length === 0">
					<?php esc_html_e( 'Δεν υπάρχουν ελεύθερες ώρες αυτή την ημέρα.', 'simple-booking' ); ?>
				</p>

				<div class="sbk-slots__grid">
					<template x-for="slot in slots" :key="slot.time">
						<button type="button" class="sbk-slot"
							:class="{ 'is-selected': selected.time === slot.time }"
							:aria-pressed="selected.time === slot.time"
							@click="pickTime(slot)" x-text="slot.time"></button>
					</template>
				</div>
			</div>
		</section>

		<!-- ── Step 5: customer details ──────────────────────────────────── -->
		<section x-show="isStep(5)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Τα στοιχεία σου', 'simple-booking' ); ?></h2>

			<div class="sbk-fields">
				<label class="sbk-field">
					<span class="sbk-field__label"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?> <span class="sbk-req">*</span></span>
					<input type="text" x-model="customer.first_name" autocomplete="given-name" required>
				</label>

				<label class="sbk-field">
					<span class="sbk-field__label"><?php esc_html_e( 'Επώνυμο', 'simple-booking' ); ?></span>
					<input type="text" x-model="customer.last_name" autocomplete="family-name">
				</label>

				<label class="sbk-field">
					<span class="sbk-field__label"><?php esc_html_e( 'Email', 'simple-booking' ); ?> <span class="sbk-req">*</span></span>
					<input type="email" x-model="customer.email" autocomplete="email" inputmode="email" required>
				</label>

				<label class="sbk-field">
					<span class="sbk-field__label"><?php esc_html_e( 'Τηλέφωνο', 'simple-booking' ); ?> <span class="sbk-req">*</span></span>
					<input type="tel" x-model="customer.phone" autocomplete="tel" inputmode="tel" required>
				</label>

				<label class="sbk-field sbk-field--wide">
					<span class="sbk-field__label"><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></span>
					<textarea x-model="note" rows="3" placeholder="<?php esc_attr_e( 'Προαιρετικό', 'simple-booking' ); ?>"></textarea>
				</label>
			</div>

			<label class="sbk-account">
				<input type="checkbox" x-model="createAccount">
				<span>
					<strong><?php esc_html_e( 'Θέλω λογαριασμό', 'simple-booking' ); ?></strong>
					<small><?php esc_html_e( 'Θα λάβεις email για να ορίσεις κωδικό. Δεν είναι απαραίτητο για την κράτηση.', 'simple-booking' ); ?></small>
				</span>
			</label>

			<!-- Honeypot: hidden from people, irresistible to bots -->
			<div class="sbk-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Ιστοσελίδα', 'simple-booking' ); ?>
					<input type="text" x-model="website" tabindex="-1" autocomplete="off">
				</label>
			</div>
		</section>

		<!-- ── Step 6: summary ───────────────────────────────────────────── -->
		<section x-show="isStep(6)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Σύνοψη', 'simple-booking' ); ?></h2>

			<dl class="sbk-summary">
				<div class="sbk-summary__row">
					<dt><?php esc_html_e( 'Κουρέας', 'simple-booking' ); ?></dt>
					<dd>
						<span x-text="selected.staff?.name"></span>
						<button type="button" class="sbk-link" @click="goTo(1)"><?php esc_html_e( 'αλλαγή', 'simple-booking' ); ?></button>
					</dd>
				</div>
				<div class="sbk-summary__row">
					<dt><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></dt>
					<dd>
						<span x-text="selected.service?.name"></span>
						<span class="sbk-summary__price" x-text="selected.service?.price_label"></span>
						<button type="button" class="sbk-link" @click="goTo(2)"><?php esc_html_e( 'αλλαγή', 'simple-booking' ); ?></button>
					</dd>
				</div>
				<div class="sbk-summary__row" x-show="selected.extras.length">
					<dt><?php esc_html_e( 'Extras', 'simple-booking' ); ?></dt>
					<dd>
						<template x-for="extra in selected.extras" :key="extra.id">
							<span class="sbk-summary__line">
								<span x-text="extra.name"></span>
								<span class="sbk-summary__price" x-text="'+' + extra.price_label"></span>
							</span>
						</template>
						<button type="button" class="sbk-link" @click="goTo(3)"><?php esc_html_e( 'αλλαγή', 'simple-booking' ); ?></button>
					</dd>
				</div>
				<div class="sbk-summary__row">
					<dt><?php esc_html_e( 'Πότε', 'simple-booking' ); ?></dt>
					<dd>
						<span x-text="selectedDateLabel() + ' · ' + selected.time"></span>
						<button type="button" class="sbk-link" @click="goTo(4)"><?php esc_html_e( 'αλλαγή', 'simple-booking' ); ?></button>
					</dd>
				</div>
				<div class="sbk-summary__row">
					<dt><?php esc_html_e( 'Διάρκεια', 'simple-booking' ); ?></dt>
					<dd x-text="totalDurationLabel()"></dd>
				</div>
				<div class="sbk-summary__row">
					<dt><?php esc_html_e( 'Στοιχεία', 'simple-booking' ); ?></dt>
					<dd>
						<span x-text="customerName()"></span><br>
						<span class="sbk-muted" x-text="customer.email"></span><br>
						<span class="sbk-muted" x-text="customer.phone"></span>
						<button type="button" class="sbk-link" @click="goTo(5)"><?php esc_html_e( 'αλλαγή', 'simple-booking' ); ?></button>
					</dd>
				</div>
				<div class="sbk-summary__row" x-show="note">
					<dt><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></dt>
					<dd x-text="note"></dd>
				</div>
				<div class="sbk-summary__row sbk-summary__row--total">
					<dt><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></dt>
					<dd x-text="totalLabel()"></dd>
				</div>
			</dl>
		</section>

		<!-- ── Step 7: confirm ───────────────────────────────────────────── -->
		<section x-show="isStep(7)" x-transition.opacity>
			<h2 class="sbk-h"><?php esc_html_e( 'Επιβεβαίωση', 'simple-booking' ); ?></h2>

			<div class="sbk-confirm">
				<p class="sbk-confirm__line">
					<strong x-text="selected.service?.name"></strong>
					<?php esc_html_e( 'με', 'simple-booking' ); ?>
					<strong x-text="selected.staff?.name"></strong>
				</p>
				<p class="sbk-confirm__when">
					<span x-text="selectedDateLabel()"></span> · <span x-text="selected.time"></span>
				</p>
				<p class="sbk-confirm__total" x-text="totalLabel()"></p>
			</div>

			<p class="sbk-notice">
				<?php esc_html_e( 'Η πληρωμή γίνεται στο κατάστημα. Το ραντεβού επιβεβαιώνεται αμέσως με την υποβολή.', 'simple-booking' ); ?>
			</p>
		</section>

		<!-- ── Result ────────────────────────────────────────────────────── -->
		<section x-show="result" x-transition.opacity>
			<div class="sbk-done">
				<div class="sbk-done__icon" aria-hidden="true">✓</div>
				<h2 class="sbk-h"><?php esc_html_e( 'Το ραντεβού κλείστηκε', 'simple-booking' ); ?></h2>

				<dl class="sbk-summary">
					<div class="sbk-summary__row">
						<dt><?php esc_html_e( 'Πότε', 'simple-booking' ); ?></dt>
						<dd><span x-text="result?.date_label"></span> · <span x-text="result?.time + ' – ' + result?.end_time"></span></dd>
					</div>
					<div class="sbk-summary__row">
						<dt><?php esc_html_e( 'Τι', 'simple-booking' ); ?></dt>
						<dd><span x-text="result?.service_name"></span> <?php esc_html_e( 'με', 'simple-booking' ); ?> <span x-text="result?.staff_name"></span></dd>
					</div>
					<div class="sbk-summary__row sbk-summary__row--total">
						<dt><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></dt>
						<dd x-text="result?.total_label"></dd>
					</div>
				</dl>

				<p class="sbk-notice" x-show="result?.account_created">
					<?php esc_html_e( 'Σου στείλαμε email για να ορίσεις κωδικό στον λογαριασμό σου.', 'simple-booking' ); ?>
				</p>

				<p class="sbk-notice"><?php esc_html_e( 'Η πληρωμή γίνεται στο κατάστημα. Τα λέμε σύντομα!', 'simple-booking' ); ?></p>

				<button type="button" class="sbk-btn sbk-btn--ghost" @click="reset()">
					<?php esc_html_e( 'Νέα κράτηση', 'simple-booking' ); ?>
				</button>
			</div>
		</section>

		<div class="sbk-loading" x-show="loading" x-transition.opacity>
			<span class="sbk-spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Φόρτωση…', 'simple-booking' ); ?></span>
		</div>
	</div>

	<!-- ── Navigation ────────────────────────────────────────────────── -->
	<div class="sbk-actions" x-show="!result">
		<button type="button" class="sbk-btn sbk-btn--ghost" @click="back()" x-show="showBack()">
			<?php esc_html_e( 'Πίσω', 'simple-booking' ); ?>
		</button>

		<button type="button" class="sbk-btn sbk-btn--primary" @click="next()"
			x-show="showNext()" :disabled="!canAdvance() || loading">
			<span x-text="nextLabel()"></span>
		</button>

		<button type="button" class="sbk-btn sbk-btn--primary" @click="submit()"
			x-show="isLastStep()" :disabled="loading">
			<?php esc_html_e( 'Επιβεβαίωση κράτησης', 'simple-booking' ); ?>
		</button>
	</div>
</div>
