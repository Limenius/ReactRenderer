# ReactRenderer

ReactRenderer lets you implement React.js client and server-side rendering in your PHP projects, allowing the development of universal (isomorphic) applications.

It was previously part of [ReactBundle](https://github.com/Limenius/ReactBundle) but now can be used standalone.

If you wish to use it with Silex, check out @teameh [Silex React Renderer Service Provider](https://github.com/teameh/silex-react-renderer-provider).

Features include:

* Prerender server-side React components for SEO, faster page loading, for users that have disabled JavaScript, or for Progressive Web Applications.
* Twig integration.
* Client-side render will take the server-side rendered DOM, recognize it, and take control over it without rendering again the component until needed.
* Error and debug management for server and client side code.
* Simple integration with Webpack.

[![Build Status](https://travis-ci.org/Limenius/ReactRenderer.svg?branch=master)](https://travis-ci.org/Limenius/ReactRenderer)
[![Latest Stable Version](https://poser.pugx.org/limenius/react-renderer/v/stable)](https://packagist.org/packages/limenius/react-renderer)
[![Latest Unstable Version](https://poser.pugx.org/limenius/react-renderer/v/unstable)](https://packagist.org/packages/limenius/react-renderer)
[![License](https://poser.pugx.org/limenius/react-renderer/license)](https://packagist.org/packages/limenius/react-renderer)

## Complete example

For a complete live example, with a sensible Webpack set up, a sample application to start with and integration in a Symfony Project, check out [Symfony React Sandbox](https://github.com/Limenius/symfony-react-sandbox).

## Installation

ReactRenderer uses Composer, please checkout the [composer website](http://getcomposer.org) in case of doubt about this.

This command will install `ReactRenderer` into your project.

```bash
$ composer require limenius/react-renderer
```

> ReactRenderer follows the PSR-4 convention names for its classes so you can integrate it with your autoloader.

## Usage

### JavaScript and Webpack set up

In order to use React components you need to register them in your JavaScript. This bundle makes use of the React On Rails npm package to render React Components (don't worry, you don't need to write any Ruby code! ;) ).

Your code exposing a React component would look like this:

```js
import ReactOnRails from 'react-on-rails';
import RecipesApp from './RecipesAppServer';

ReactOnRails.register({ RecipesApp });
```

Where RecipesApp is the component we want to register in this example.

Note that it is very likely that you will need separated entry points for your server-side and client-side components, for dealing with things like routing. This is a common issue with any universal (isomorphic) application. Again, see the sandbox for an example of how to deal with this.

If you use server-side rendering, you are also expected to have a Webpack bundle for it, containing React, React on Rails and your JavaScript code that will be used to evaluate your component.

Take a look at [the Webpack configuration in the symfony-react-sandbox](https://github.com/Limenius/symfony-react-sandbox/blob/master/webpack.config.serverside.js) for more information.

### Enable Twig Extension

First, you need to configure and enable the Twig extension.

```php
use Limenius\ReactRenderer\Renderer\PhpExecJsReactRenderer;
use Limenius\ReactRenderer\Twig\ReactRenderExtension;
use Limenius\ReactRenderer\Context\SymfonyContextProvider;

// SymfonyContextProvider provides information about the current request, such as hostname and path
// We need an instance of Symfony\Component\HttpFoundation\RequestStack to use it
$contextProvider = new SymfonyContextProvider($requestStack);
$renderer = new PhpExecJsReactRenderer(__DIR__.'/client/build/server-bundle.js', false, $contextProvider);
$ext = new ReactRenderExtension($renderer, $contextProvider, 'both');

$twig->addExtension(new Twig_Extension_StringLoader());
$twig->addExtension($ext);
```

`ReactRenderExtension` needs as arguments a *renderer* and a string that defines if we are rendering our React components `client_side`, `render_side` or `both`.

The renderer is one of the renders that inherit from [`AbstractReactRenderer`](ReactRenderer/src/Limenius/ReactRenderer/Renderer/AbstractReactRenderer.php).

This library provides currently two renderers:

* `PhpExecJsReactRenderer`: that uses internally [phpexecjs](https://github.com/nacmartin/phpexecjs) to autodetect the best javascript runtime available.
* `ExternalServerReactRenderer`: that relies on a external nodeJs server.

Now you can insert React components in your Twig templates with:

```twig
{{ react_component('RecipesApp', {'props': props}, false) }}
```

Where `RecipesApp` is, in this case, the name of our component, and `props` are the props for your component. Props can either be a JSON encoded string or an array. 

For instance, a controller action that will produce a valid props could be:

```php
/**
 * @Route("/recipes", name="recipes")
 */
public function homeAction(Request $request)
{
    $serializer = $this->get('serializer');
    return $this->render('recipe/home.html.twig', [
        'props' => $serializer->serialize(
            ['recipes' => $this->get('recipe.manager')->findAll()->recipes], 'json')
    ]);
}
```

If you set the last parameter of `react_component` to `true` instead of `false` the context and `props` are not immediately included in the template. All this data is buffered and can be inserted right before the closing body tag with:

```twig
{{ react_flush_buffer() }}
```
This is recommend if you have a lot of `props` and don't want to include them in the first parts of your HTML response. See
 
 https://developers.google.com/speed/docs/insights/PrioritizeVisibleContent

### Server-side, client-side or both?

You can choose whether your React components will be rendered only client-side, only server-side or both, either in the configuration as stated above or per Twig tag basis.

If you set the option `rendering` of the Twig call, you can override your config (default is to render both server-side and client-side).

```twig
{{ react_component('RecipesApp', {'props': props, 'rendering': 'client_side'}) }}
```

Will render the component only client-side, whereas the following code

```twig
{{ react_component('RecipesApp', {'props': props, 'rendering': 'server_side'}) }}
```

... will render the component only server-side (and as a result the dynamic components won't work).

Or both (default):

```twig
{{ react_component('RecipesApp', {'props': props, 'rendering': 'both'}) }}
```

You can explore these options by looking at the generated HTML code.

### Debugging

One important point when running server-side JavaScript code from PHP is the management of debug messages thrown by `console.log`. ReactRenderer, inspired React on Rails, has means to replay `console.log` messages into the JavaScript console of your browser.

To enable tracing, you can set a config parameter, as stated above, or you can set it in your template in this way:

```twig
{{ react_component('RecipesApp', {'props': props, 'trace': true}) }}
```

Note that in this case you will probably see a React warning like

*"Warning: render(): Target node has markup rendered by React, but there are unrelated nodes as well. This is most commonly caused by white-space inserted around server-rendered markup."*

This warning is harmless and will go away when you disable trace in production. It means that when rendering the component client-side and comparing with the server-side equivalent, React has found extra characters. Those characters are your debug messages, so don't worry about it.

### Context

This library will provide context about the current request to React components. Your components will receive two arguments on instantiation:

```js
const App = (initialProps, context) => {
}
```

The Symfony context provider has this implementation:

```php
    public function getContext($serverSide)
    {
        $request = $this->requestStack->getCurrentRequest();

        return [
            'serverSide' => $serverSide,
            'href' => $request->getSchemeAndHttpHost().$request->getRequestUri(),
            'location' => $request->getRequestUri(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'base' => $request->getBaseUrl(),
            'pathname' => $request->getPathInfo(),
            'search' => $request->getQueryString(),
        ];
    }

```

So you can access these properties in your React components, to get information about the request, and if it has been rendered server side or client side.

### Server-Side modes

This library supports two modes of using server-side rendering:

* Using [PhpExecJs](https://github.com/nacmartin/phpexecjs) to auto-detect a JavaScript environment (call node.js via terminal command or use V8Js PHP) and run JavaScript code through it.

* Using an external node.js server ([Example](https://github.com/Limenius/symfony-react-sandbox/blob/master/external-server.js). It will use a dummy server, that knows nothing about your logic to render React for you. Introduces more operational complexity (you have to keep the node server running, which is not a big deal anyways).

Currently, the best option is to use an external server in production, since having [V8js](https://github.com/phpv8/v8js) is rather hard to compile. However, if you can compile it or your distribution/OS has good packages, it is a very good option if you enable caching, as we will see in the next section.

### Cache

if in your config.prod.yaml or `config/packages/prod/limenius_react.yaml` you add the following configuration, and you have V8js installed, this bundle will be much faster:
```yaml
limenius_react:
    serverside_rendering:
        cache:
            enabled: true
            # name of your app, it is the key of the cache where the snapshot will be stored.
            key: "recipes_app"
```
After the first page render, this will store a snapshot of the JS virtual machine V8js in the cache, so in subsequent visits, your whole JavaScript app doesn't need to be processed again, just the particular component that you want to render.

With the cache enabled, if you change code of your JS app, you will need to clear the cache.

### Redux

If you're using [Redux](http://redux.js.org/) you could use this library to hydrate your store's:

Use `redux_store` in your Twig file before you render your components depending on your store:

```twig
{{ redux_store('MySharedReduxStore', initialState ) }}
{{ react_component('RecipesApp') }}
```
`MySharedReduxStore` here is the identifier you're using in your javascript to get the store. The `initialState` can either be a JSON encoded string or an array. 

Then, expose your store in your bundle, just like your exposed your components:

```js
import ReactOnRails from 'react-on-rails';
import RecipesApp from './RecipesAppServer';
import configureStore from './store/configureStore';

ReactOnRails.registerStore({ configureStore });
ReactOnRails.register({ RecipesApp });
```

Finally use `ReactOnRails.getStore` where you would have used your the object you passed into `registerStore`.

```js
// Get hydrated store
const store = ReactOnRails.getStore('MySharedReduxStore');

return (
  <Provider store={store}>
    <Scorecard />
  </Provider>
);
```

Make sure you use the same identifier here (`MySharedReduxStore`) as you used in your Twig file to set up the store. 

You have an example in the [Sandbox](https://github.com/Limenius/symfony-react-sandbox).

## Generator Functions

Instead of returning a component, you may choose to return an object from your JavaScript code.

One use case for this is to render Title or other meta tags in Server Side Rendering with [React Helmet](https://github.com/nfl/react-helmet). You may want to return the generated HTML of the component along with the title.

```js 
export default (initialProps, context) => {
    const renderedHtml = {
      componentHtml: renderToString(
        <MyApp/>
      ),
      title: Helmet.renderStatic().title.toString()
    };
    return { renderedHtml };
}
```

In these cases, the primary HTML code that is going to be rendered must be in the key `componentHtml`. You can access the resulting array in Twig:


```twig
{% set recipes = react_component_array('RecipesApp', {'props': props}) %}
{% block title %}
  {{ recipes.title is defined ? recipes.title | raw : '' }}
{% endblock title %}

{% block body %}
  {{ recipes.componentHtml | raw }}
{% endblock %}
```

There is an example of this in the sandbox.

## License

This library is under the MIT license. See the complete license in the bundle:

    LICENSE.md

## Credits

ReactRenderer is heavily inspired by the great [React On Rails](https://github.com/shakacode/react_on_rails), and uses its npm package to render React components.
