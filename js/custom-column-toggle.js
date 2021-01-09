/**
 * Index Me ajax status change handling
 *
 *
 * Uses on_ajax_update_index_me_meta() in multisite-portfolio.php
 *
 * @author Per Soderlind <per@soderlind.no>
 */

document.addEventListener(
	"DOMContentLoaded",
	() => {
		let checkboxes = document.getElementsByClassName("tgl");

		for (let cb in checkboxes) {
			checkboxes[cb].onclick = async (event) => {
				event.preventDefault();

				const self = event.currentTarget;

				const data = new FormData();
				data.append("action", `${customColumnToggle.column_id}_update_option`);
				data.append("security", customColumnToggle.nonce);
				data.append("data_id", self.dataset.dataid);
				data.append("change_to", self.dataset.changeto);

				const url = `${customColumnToggle.ajaxurl}?now=${escape(
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
