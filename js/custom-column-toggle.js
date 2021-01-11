/**
 * WP_Table Custom Toggle Column ajax status change handling
 *
 * Uses on_ajax_update_option in class-wp-table-custom-column-toggle.php
 *
 * @see https://github.com/soderlind/class-wp-table-custom-column-toggle/blob/main/class-wp-table-custom-column-toggle.php
 * @author Per Soderlind <per@soderlind.no>
 */

document.addEventListener(
	"DOMContentLoaded",
	() => {
		let checkboxes = document.getElementsByClassName("custom-tgl");

		for (let cb in checkboxes) {
			checkboxes[cb].onclick = async (event) => {
				event.preventDefault();

				const self = event.currentTarget;
				const handleObject = window[self.dataset.handle];

				const data = new FormData();
				data.append("action", `${handleObject.column_id}_update_option`);
				data.append("security", handleObject.nonce);
				data.append("data_id", self.dataset.dataid);
				data.append("change_to", self.dataset.changeto);

				const url = `${handleObject.ajaxurl}?now=${escape(
					new Date().getTime().toString(),
				)}`;
				try {
					const response = await fetch(
						url,
						{
							method: "POST",
							credentials: "same-origin",
							body: data,
						},
					);

					const res = await response.json();
					if (res.response === "success") {
						self.checked ^= 1; // toggle checkbox on/off.
						self.dataset.changeto = res.change_to;
					} else {
						console.error(res);
					}
				} catch (err) {
					console.error(err);
				}
			};
		}
	},
);
