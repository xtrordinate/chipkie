import { en } from '@formkit/i18n'
import { defaultConfig } from '@formkit/vue'
import { rootClasses } from './formkit.theme'

const legends = ['checkbox_multi', 'radio_multi', 'repeater', 'transferlist'];

/**
 * Append asterisk to the label of Formkit input if it's required
 */
function addAsteriskPlugin(node) {
  if (["button", "submit", "hidden", "group", "list", "meta"].includes(node.props.type)) return;

  node.on("created", () => {
    const legendOrLabel = legends.includes(`${node.props.type}${node.props.options ? "_multi" : ""}`) ? "legend" : "label";

    if (node.props.definition.schemaMemoKey) {
      node.props.definition.schemaMemoKey += `${node.props.options ? "_multi" : ""}_add_asterisk`;
    }

    const schemaFn = node.props.definition.schema;
    node.props.definition.schema = (sectionsSchema = {}) => {
      sectionsSchema[legendOrLabel] = {
        children: [
          "$label",
          {
            $el: "span",
            if: "$state.required",
            attrs: {
              class: "$classes.asterisk",
            },
            children: ["*"],
          },
        ],
      };

      return schemaFn(sectionsSchema);
    };
  });
}

export default defaultConfig({
  locales: { en },
  locale: 'en',
  config: {
    rootClasses,
  },
  plugins: [addAsteriskPlugin]
})