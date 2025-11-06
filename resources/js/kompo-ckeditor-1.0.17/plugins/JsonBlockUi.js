import {ButtonView, Plugin} from 'ckeditor5';

export class JsonBlockUI extends Plugin {
  init() {
    const editor = this.editor;
    editor.ui.componentFactory.add('jsonBlock', locale => {
      const command = editor.commands.get('jsonBlock');
      const button = new ButtonView(locale);
      button.set({
        label: 'JSON Block',
        withText: true,  // Displays the label as text (you could use a custom icon)
        tooltip: 'Insert a JSON block'
      });
      // Bind the button state to the command
      button.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
      // Execute the command when clicked
      this.listenTo(button, 'execute', () => editor.execute('jsonBlock'));
      return button;
    });
  }
}
