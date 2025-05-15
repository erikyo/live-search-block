/* Defining an interface. */
export interface TextDef {
	message: string;
}

declare global {
	export interface Window {
		liveSearchBlock: {
			text: TextDef;
			formRedirectUrl: string;
		};
	}
}
