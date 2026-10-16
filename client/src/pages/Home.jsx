import { useCallback, useEffect, useState } from 'react'
import { api } from '../lib/api'
import Map, { useControl, Source, Layer } from 'react-map-gl/mapbox'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import 'mapbox-gl/dist/mapbox-gl.css'
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css'
import { createShipOperationPerimeter, getShipPerimeters } from '../lib/operations/shipOperations'

function DrawControl({ onCreate, onUpdate, onDelete }) {
    useControl(
        () =>
            new MapboxDraw({
                displayControlsDefault: false,
                controls: {
                    polygon: true,
                    trash: true,
                },
            }),
        ({ map }) => {
            map.on('draw.create', onCreate)
            map.on('draw.update', onUpdate)
            map.on('draw.delete', onDelete)
        },
        ({ map }) => {
            map.off('draw.create', onCreate)
            map.off('draw.update', onUpdate)
            map.off('draw.delete', onDelete)
        },
        { position: 'top-right' },
    )

    return null
}

export default function Home() {
    const [polygons, setPolygons] = useState({})
    const [savedPerimeters, setSavedPerimeters] = useState({
        type: 'FeatureCollection',
        features: [],
    })

    const feature = Object.values(polygons)[0]

    const onUpdate = useCallback((event) => {
        setPolygons((current) => {
            const next = { ...current }
            for (const feature of event.features) {
                next[feature.id] = feature
            }
            return next
        })
    }, [])

    const onDelete = useCallback((event) => {
        setPolygons((current) => {
            const next = { ...current }
            for (const feature of event.features) {
                delete next[feature.id]
            }
            return next
        })
    }, [])

    const handleGetShipPerimeters = useCallback(async () => {
        const perimeters = await getShipPerimeters(1)
        setSavedPerimeters({
            type: 'FeatureCollection',
            features: perimeters.map((row) => ({
                type: 'Feature',
                id: row.id,
                properties: { id: row.id },
                geometry: row.perimeter,
            })),
        })
    }, [])

    return (
        <div>
            <p>{Object.keys(polygons).length} polígono(s)</p>
            <button disabled={!feature} onClick={() => createShipOperationPerimeter(1, feature.geometry)}>Criar Perímetro</button>
            <button onClick={() => handleGetShipPerimeters(1)}>Buscar Perimetros</button>
            <Map
                mapboxAccessToken={import.meta.env.VITE_MAPBOX_ACCESS_TOKEN}
                initialViewState={{
                    longitude: -48.6584315,
                    latitude: -28.2340501,
                    zoom: 14,
                }}
                style={{ width: '100%', height: '100vh' }}
                mapStyle="mapbox://styles/mapbox/standard"
            >
                <DrawControl
                    onCreate={onUpdate}
                    onUpdate={onUpdate}
                    onDelete={onDelete}
                />
                <Source id="saved-perimeters" type="geojson" data={savedPerimeters}>
                    <Layer
                        id="saved-perimeters-fill"
                        type="fill"
                        paint={{ 'fill-color': '#3b82f6', 'fill-opacity': 0.3 }}
                    />
                    <Layer
                        id="saved-perimeters-line"
                        type="line"
                        paint={{ 'line-color': '#3b82f6', 'line-width': 2 }}
                    />
                </Source>
            </Map>
        </div>
    )
}
